<?php
require_once __DIR__ . "/ImageResizer.class.php";

class QueueItem
{
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

	private function setStatus($status, $message = null)
	{
		$this->queueData->status = $status;
		$this->queueData->message = $message;

		$this->saveQueueFile();
	}

	public function run()
	{
		$this->setStatus("preparing");

		$folderName = $this->queueData->folder;
		$year = $this->queueData->year;

		$sourcePath = $this->picturesSourcePath . "/" . $year . "/" . $folderName;
		$albumPath = $this->albumsPath . "/" . $year . "/" . $folderName;

		if (!is_dir($albumPath))
		{
			mkdir($albumPath, 0777, true);
		}

		$albumId = null;

		$albumInfoFile = $albumPath . "/album.xml";
		if (file_exists($albumInfoFile))
		{
			$albumInfoDocument = new DOMDocument();
			$albumInfoDocument->load($albumInfoFile);
			$rootNode = $albumInfoDocument->getElementsByTagName("album")->item(0);

			$albumId = $rootNode->getAttribute("id");

			$albumInfoDocument = null;
		}

		// Build the album.xml
		$albumInfoDocument = new DOMDocument();
		$rootNode = $albumInfoDocument->createElement("album");
		$albumInfoDocument->appendChild($rootNode);

		$yearNode = $albumInfoDocument->createElement("year");
		$yearNode->nodeValue = $year;
		$rootNode->appendChild($yearNode);

		$folderNameNode = $albumInfoDocument->createElement("foldername");
		$folderNameNode->nodeValue = $folderName;
		$rootNode->appendChild($folderNameNode);

		$picturesNode = $albumInfoDocument->createElement("pictures");
		$rootNode->appendChild($picturesNode);

		$validFiles = array();
		$imageNumber = 1;

		$this->setStatus("resizingImages");

		// Resize all images
		$dir = scandir($sourcePath);
		sort($dir, SORT_NATURAL);
		foreach ($dir as $file)
		{
			if ($file[0] != "." and is_file($sourcePath . "/" . $file) and strtolower(pathinfo($file, PATHINFO_EXTENSION)) == "jpg")
			{
				$name = md5_file($sourcePath . "/" . $file);

				$largeFile = $albumPath . "/large_" . $name . ".jpg";
				$smallFile = $albumPath . "/small_" . $name . ".jpg";

				if (!file_exists($largeFile) and !file_exists($smallFile))
				{
					$resizer = new ImageResizer(imagecreatefromjpeg($sourcePath . "/" . $file));

					if (!file_exists($largeFile))
					{
						imagejpeg($resizer->resize(1500, 1000), $largeFile);
					}

					if (!file_exists($smallFile))
					{
						imagejpeg($resizer->resize(600, 200), $smallFile);
					}

					$resizer = null;
				}

				// Add the picture to the album.xml
				$pictureNode = $albumInfoDocument->createElement("picture");
				$pictureNode->setAttribute("name", $name);
				$pictureNode->setAttribute("number", $imageNumber);
				$picturesNode->appendChild($pictureNode);

				$validFiles[] = $largeFile;
				$validFiles[] = $smallFile;

				$imageNumber++;
			}
		}

		$this->setStatus("cleanup");

		// Remove old files (e.g. deleted from source folder)
		$dir = scandir($albumPath);
		foreach ($dir as $file)
		{
			if (!in_array($file, $validFiles))
			{
				unlink($albumPath . "/" . $file);
			}
		}

		$this->setStatus("uploading");

		if ($albumId)
		{
			$albumFolderName = $albumId;
		}
		else
		{
			$albumFolderName = "tmp_" . md5($year . "/" . $folderName);// Create an unique temporary folder name
		}

		$remotePath = $this->remoteWebsiteRoot . "/files/pictures/" . $albumFolderName;

		$output = array();

		$rsyncCommand = array
		(
			"rsync",
			"-rz",
			"--delete",
			"--log-file=" . $this->rsyncLogFile,
			"--rsync-path=\"sudo mkdir -p " . $remotePath . " && sudo rsync\"",
			"-e \"ssh -i " . $this->sshServer->privateKeyFile . "\"",
			"\"" . $albumPath . "\"",
			$this->sshServer->username . "@" . $this->sshServer->server . ":" . $remotePath . "/"
		);
		exec($rsyncCommand, $output, $returnCode);

		if ($returnCode)
		{
			$this->setStatus("error", "rsync error:\n" . implode("\n", $output));
			return false;
		}

		$this->setStatus("updatingDatabase");

		$sshConnection = ssh2_connect($this->sshServer->server);
		if (!ssh2_auth_pubkey_file($sshConnection, $this->sshServer->username, $this->sshServer->publicKeyFile, $this->sshServer->privateKeyFile))
		{
			$this->setStatus("error", "SSH authentication failed!");
			return false;
		}

		$outputStream = ssh2_exec($sshConnection, "sudo php" . $this->remoteWebsiteRoot . "/addAlbum.php " . $albumFolderName);

		stream_set_blocking($outputStream, true);

		$albumId = stream_get_contents($outputStream);// addAlbum.php returns the album ID on success

		if (!$albumId or !is_numeric($albumId))
		{
			$this->setStatus("error", "Error while execution of addAlbum.php on " . $this->sshServer->server . ":\n" . $albumId);
			return false;
		}

		$this->setStatus("writingAlbumInfo");

		$rootNode->setAttribute("id", $albumId);// Set the new album ID
		$albumInfoDocument->save($albumInfoFile);

		$albumInfoDocument = null;

		return true;
	}
}