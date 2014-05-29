<?php
class QueueItem
{
	private $queueData;
	private $albumsPath;
	private $remoteWebsiteRoot;
	private $rsyncLogFile;
	private $sshServer;

	public function __construct($queueFile, $albumsPath, $remoteWebsiteRoot, $rsyncLogFile, SshServer $sshServer)
	{
		$this->queueFile = $queueFile;
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
		$this->setStatus("processing");

		$folderName = $this->queueData->folder;
		$year = $this->queueData->year;

		$albumPath = $this->albumsPath . "/" . $year . "/" . $folderName;

		$albumInfoFile = $albumPath . "/album.xml";

		if (!file_exists($albumInfoFile))
		{
			$this->setStatus("error", "No album.xml found!");
			return false;
		}

		$document = new DOMDocument();
		$document->load($albumInfoFile);
		$root = $document->getElementsByTagName("album")->item(0);

		$albumId = $root->getAttribute("id");

		if ($albumId)
		{
			$albumFolderName = $albumId;
		}
		else
		{
			$year = $root->getElementsByTagName("year")->item(0)->noideValue;
			$folderName = $root->getElementsByTagName("foldername")->item(0)->nodeValue;
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

		$root->setAttribute("id", $albumId);// Set the new album ID
		$document->save($albumInfoFile);

		return true;
	}
}