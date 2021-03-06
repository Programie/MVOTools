<?php
namespace com\selfcoders\mvotools\pictureuploader\queue;

use com\selfcoders\mvotools\pictureuploader\image\Resizer;
use com\selfcoders\mvotools\pictureuploader\Logger;
use com\selfcoders\mvotools\pictureuploader\object\Album;
use com\selfcoders\mvotools\pictureuploader\object\State;

class Item
{
	/**
	 * @var Album
	 */
	private $album;

	public function __construct($file)
	{
		$data = json_decode(file_get_contents(QUEUE_PATH . "/" . $file));

		$this->album = new Album;

		$this->album->year = (int) $data->year;
		$this->album->album = basename($data->album);
	}

	public static function getPath($year, $album)
	{
		return QUEUE_PATH . "/" . $year . "_" . $album . ".json";
	}

	public static function create($year, $album)
	{
		file_put_contents(self::getPath($year, $album), json_encode(array
		(
			"year" => $year,
			"album" => basename($album)
		)));
	}

	private function setState($state, $current = null, $total = null)
	{
		State::save($this->album->year, $this->album->album, $state, $current, $total);
	}

	private function setErrorState($content)
	{
		Logger::log("Error: " . $content);
		State::saveError($this->album->year, $this->album->album, $content);
	}

	private function getPictures()
	{
		$path = $this->album->getPicturesPath();

		$dir = scandir($path);

		sort($dir, SORT_NATURAL);

		$files = array();

		foreach ($dir as $file)
		{
			if ($file[0] == ".")
			{
				continue;
			}

			if (!is_file($path . "/" . $file))
			{
				continue;
			}

			if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) != "jpg")
			{
				continue;
			}

			$files[] = $file;
		}

		return $files;
	}

	public function process()
	{
		$picturesPath = $this->album->getPicturesPath();
		$dataPath = $picturesPath . "/" . DATA_FOLDER;

		Logger::log("Processing album: " . $picturesPath);

		if (!is_dir($dataPath))
		{
			mkdir($dataPath, 0775);
		}

		$this->album->load();

		$pictures = $this->getPictures();

		$validFiles = array("album.json");

		$this->album->pictures = array();

		Logger::log("Preparing " . count($pictures) . " pictures");

		foreach ($pictures as $index => $file)
		{
			$this->setState(State::STATE_RESIZE, $index + 1, count($pictures));

			$resizer = new Resizer($picturesPath . "/" . $file);

			$md5 = md5_file($picturesPath . "/" . $file);

			$largeFile = "large_" . $md5 . ".jpg";
			$smallFile = "small_" . $md5 . ".jpg";

			$largFilePath = $dataPath . "/" . $largeFile;
			$smallFilePath = $dataPath . "/" . $smallFile;

			if (!file_exists($largFilePath))
			{
				Logger::log("Saving large version of " . $file);
				imagejpeg($resizer->resize(1500, 1000), $largFilePath);
			}

			if (!file_exists($smallFilePath))
			{
				Logger::log("Saving small version of " . $file);
				imagejpeg($resizer->resize(600, 200), $smallFilePath);
			}

			$validFiles[] = $largeFile;
			$validFiles[] = $smallFile;

			$this->album->pictures[] = $md5;
		}

		$this->album->save();

		$this->setState(State::STATE_CLEANUP);

		foreach (scandir($dataPath) as $file)
		{
			if ($file == "." or $file == "..")
			{
				continue;
			}

			if (in_array($file, $validFiles))
			{
				continue;
			}

			Logger::log("Removing old file from workspace: " . $file);

			unlink($dataPath . "/" . $file);
		}

		$this->setState(State::STATE_UPLOAD);

		if ($this->album->id)
		{
			$albumFolder = $this->album->id;
		}
		else
		{
			$albumFolder = "tmp_" . md5($this->album->year . "/" . $this->album->album);
		}

		$remotePath = REMOTE_WEBSITE_ROOT . "/files/pictures/" . $albumFolder;

		Logger::log("Uploading to path: " . $remotePath);

		$returnCode = -1;

		$rsyncCommand = array
		(
			"rsync",
			"--compress",
			"--recursive",
			"--delete",
			"--progress",
			"--log-file=%s",
			"--rsync-path=\"mkdir -p %s && rsync\"",
			"--exclude state.json",
			"-e \"ssh -i %s\"",
			"\"%s/\" %s/"
		);

		$target = sprintf("%s@%s:%s", SSH_USERNAME, SSH_SERVER, $remotePath);

		$rsyncCommand = sprintf(implode(" ", $rsyncCommand), RSYNC_LOG_FILE, $remotePath, SSH_PRIVATE_KEY, $dataPath, $target);

		Logger::log("Executing command: " . $rsyncCommand);

		$rsyncProcess = popen($rsyncCommand, "r");
		if ($rsyncProcess)
		{
			while (!feof($rsyncProcess))
			{
				$line = fgets($rsyncProcess);

				// Parse the following line:
				// 741 100%    6.08kB/s    0:00:00 (xfr#1580, to-chk=2/1763)
				if (preg_match("/to-chk=([0-9]+)\/([0-9]+)\)/", $line, $matches))
				{
					$total = (int) $matches[2];
					$remaining = (int) $matches[1];

					$this->setState(State::STATE_UPLOAD, $total - $remaining, $total);
				}
			}

			$returnCode = pclose($rsyncProcess);
		}

		if ($returnCode)
		{
			$this->setErrorState("Rsync error");
			return false;
		}

		$this->setState(State::STATE_UPDATE_DATABASE);

		Logger::log("Updating database");

		$sshConnection = ssh2_connect(SSH_SERVER);
		if (!ssh2_auth_pubkey_file($sshConnection, SSH_USERNAME, SSH_PUBLIC_KEY, SSH_PRIVATE_KEY))
		{
			$this->setErrorState("SSH authentication failed!");
			return false;
		}

		$command = "php " . REMOTE_WEBSITE_ROOT . "/tools/addAlbum.php " . $albumFolder;

		Logger::log("Executing on " . SSH_SERVER . ": " . $command);

		$outputStream = ssh2_exec($sshConnection, $command);

		stream_set_blocking($outputStream, true);

		$response = stream_get_contents($outputStream);// addAlbum.php returns the album ID on success

		if (!$response or !is_numeric($response))
		{
			$content = "Output: " . $response . "\n";
			$content .= "Error: " . stream_get_contents(ssh2_fetch_stream($outputStream , SSH2_STREAM_STDERR));

			$this->setErrorState("Error while execution:\n" . $content);
			return false;
		}

		$this->album->id = (int) $response;

		Logger::log("Got album ID: " . $this->album->id);

		$this->album->save();

		$this->setState(State::STATE_DONE);

		return true;
	}

	public function removeQueueFile()
	{
		Logger::log("Removing queue item");

		unlink(self::getPath($this->album->year, $this->album->album));
	}
}