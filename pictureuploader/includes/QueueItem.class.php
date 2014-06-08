<?php
require_once __DIR__ . "/ImageResizer.class.php";

class QueueItem
{
	const STATUS_NEW = "new";
	const STATUS_PREPARING = "preparing";
	const STATUS_RESIZING_IMAGES = "resizingImages";
	const STATUS_CLEANUP = "cleanup";
	const STATUS_UPLOADING = "uploading";
	const STATUS_UPDATING_DATABASE = "updatingDatabase";
	const STATUS_WRITING_ALBUM_INFO = "writingAlbumInfo";
	const STATUS_ERROR = "error";

	private $queueData;
	private $picturesSourcePath;
	private $albumsPath;
	private $remoteWebsiteRoot;
	private $rsyncLogFile;
	private $sshServer;

	public function __construct($queueFile, $picturesSourcePath, $albumsPath, $remoteWebsiteRoot, $rsyncLogFile, SshServer $sshServer)
	{
		$this->queueFile = $queueFile;
		$this->picturesSourcePath = $picturesSourcePath;
		$this->albumsPath = $albumsPath;
		$this->remoteWebsiteRoot = $remoteWebsiteRoot;
		$this->rsyncLogFile = $rsyncLogFile;
		$this->sshServer = $sshServer;

		$this->queueData = json_decode(file_get_contents($this->queueFile));
	}

	private function saveQueueFile()
	{
		file_put_contents($this->queueFile, json_encode($this->queueData));
	}

	private function setStatus($status, $data = null)
	{
		$statusObject = new StdClass;
		$statusObject->status = $status;
		$statusObject->data = $data;

		$this->queueData->status = $statusObject;

		$this->saveQueueFile();
	}

	public function run()
	{
		$this->setStatus(QueueItem::STATUS_PREPARING);

		$folderName = $this->queueData->folder;
		$year = $this->queueData->year;

		$sourcePath = $this->picturesSourcePath . "/" . $year . "/" . $folderName;
		$albumPath = $this->albumsPath . "/" . $year . "/" . $folderName;

		if (!is_dir($albumPath))
		{
			mkdir($albumPath, 0777, true);
		}

		$albumId = null;

		$albumInfoFile = $albumPath . "/album.json";
		if (file_exists($albumInfoFile))
		{
			$albumInfoData = json_decode(file_get_contents($albumInfoFile));

			$albumId = $albumInfoData->id;

			$albumInfoDocument = null;
		}

		// Build the album.json
		$albumInfoData = new StdClass;
		$albumInfoData->id = (int) $albumId;
		$albumInfoData->year = (int) $year;
		$albumInfoData->folderName = $folderName;

		$picturesInfoData = array();

		$validFiles = array("album.json");
		$imageNumber = 1;

		// Resize all images
		$dir = scandir($sourcePath);
		sort($dir, SORT_NATURAL);
		foreach ($dir as $file)
		{
			if ($file[0] != "." and is_file($sourcePath . "/" . $file) and strtolower(pathinfo($file, PATHINFO_EXTENSION)) == "jpg")
			{
				$this->setStatus(QueueItem::STATUS_RESIZING_IMAGES, $imageNumber);

				$name = md5_file($sourcePath . "/" . $file);

				$largeFile = "large_" . $name . ".jpg";
				$smallFile = "small_" . $name . ".jpg";

				$largeFilePath = $albumPath . "/" . $largeFile;
				$smallFilePath = $albumPath . "/" . $smallFile;

				if (!file_exists($largeFilePath) or !file_exists($smallFilePath))
				{
					$resizer = new ImageResizer(imagecreatefromjpeg($sourcePath . "/" . $file));

					if (!file_exists($largeFilePath))
					{
						imagejpeg($resizer->resize(1500, 1000), $largeFilePath);
					}

					if (!file_exists($smallFilePath))
					{
						imagejpeg($resizer->resize(600, 200), $smallFilePath);
					}

					$resizer = null;
				}

				// Add the picture to the album info data
				$pictureInfoData = new StdClass;
				$pictureInfoData->name = $name;
				$pictureInfoData->number = $imageNumber;
				$picturesInfoData[] = $pictureInfoData;

				$validFiles[] = $largeFile;
				$validFiles[] = $smallFile;

				$imageNumber++;
			}
		}

		$albumInfoData->pictures = $picturesInfoData;

		file_put_contents($albumInfoFile, json_encode($albumInfoData, JSON_PRETTY_PRINT));

		$this->setStatus(QueueItem::STATUS_CLEANUP);

		// Remove old files (e.g. deleted from source folder)
		$dir = scandir($albumPath);
		foreach ($dir as $file)
		{
			if ($file[0] != "." and !in_array($file, $validFiles))
			{
				unlink($albumPath . "/" . $file);
			}
		}

		if ($albumId)
		{
			$albumFolderName = $albumId;
		}
		else
		{
			$albumFolderName = "tmp_" . md5($year . "/" . $folderName);// Create an unique temporary folder name
		}

		$remotePath = $this->remoteWebsiteRoot . "/files/pictures/" . $albumFolderName;

		$returnCode = -1;

		$rsyncCommand = array
		(
			"rsync",
			"--compress",
			"--recursive",
			"--delete",
			"--progress",
			"--log-file=" . $this->rsyncLogFile,
			"--rsync-path=\"sudo mkdir -p " . $remotePath . " && sudo rsync\"",
			"-e \"ssh -i " . $this->sshServer->privateKeyFile . "\"",
			"\"" . $albumPath . "/\"",
			$this->sshServer->username . "@" . $this->sshServer->server . ":" . $remotePath . "/"
		);
		$rsyncProcess = popen(implode(" ", $rsyncCommand), "r");
		if ($rsyncProcess)
		{
			while (!feof($rsyncProcess))
			{
				$line = fgets($rsyncProcess);

				// Parse the following line:
				// 57699 100%   63.03kB/s    0:00:00 (xfer#747, to-check=1203/2213)
				if (preg_match("/([0-9]+)%\s+([0-9\.]+)kB\/s\s+[0-9:]+\s+\(xfer#([0-9]+),\s+to-check=([0-9]+)\/([0-9]+)\)/", $line, $matches))
				{
					$rsyncData = new StdClass;
					$rsyncData->percent = $matches[1];
					$rsyncData->speed = $matches[2];
					$rsyncData->transfer = $matches[3];
					$rsyncData->toCheck = $matches[4];
					$rsyncData->totalCheck = $matches[5];
					$this->setStatus(QueueItem::STATUS_UPLOADING, $rsyncData);
				}
			}

			$returnCode = pclose($rsyncProcess);
		}

		if ($returnCode)
		{
			$this->setStatus(QueueItem::STATUS_ERROR, "rsync error");
			return false;
		}

		$this->setStatus(QueueItem::STATUS_UPDATING_DATABASE);

		$sshConnection = ssh2_connect($this->sshServer->server);
		if (!ssh2_auth_pubkey_file($sshConnection, $this->sshServer->username, $this->sshServer->publicKeyFile, $this->sshServer->privateKeyFile))
		{
			$this->setStatus(QueueItem::STATUS_ERROR, "SSH authentication failed!");
			return false;
		}

		$outputStream = ssh2_exec($sshConnection, "sudo php " . $this->remoteWebsiteRoot . "/addAlbum.php " . $albumFolderName);

		stream_set_blocking($outputStream, true);

		$albumId = stream_get_contents($outputStream);// addAlbum.php returns the album ID on success

		if (!$albumId or !is_numeric($albumId))
		{
			$content = "Output: " . $albumId . "\n";
			$content .= "Error: " . stream_get_contents(ssh2_fetch_stream($outputStream , SSH2_STREAM_STDERR));

			$this->setStatus(QueueItem::STATUS_ERROR, "Error while execution of addAlbum.php on " . $this->sshServer->server . ":\n" . $content);
			return false;
		}

		$this->setStatus(QueueItem::STATUS_WRITING_ALBUM_INFO);

		$albumInfoData->id = (int) $albumId;

		file_put_contents($albumInfoFile, json_encode($albumInfoData, JSON_PRETTY_PRINT));

		return true;
	}
}