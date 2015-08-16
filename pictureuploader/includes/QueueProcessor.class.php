<?php
require_once __DIR__ . "/QueueItem.class.php";

class QueueProcessor
{
	private $sshServer;
	private $picturesSourcePath;
	private $remoteWebsiteRoot;
	private $rsyncLogFile;
	private $albumsPath;
	private $queuePath;
	private $maxLoopCount = 150;

	public function setSshServer(SshServer $server)
	{
		$this->sshServer = $server;
	}

	public function setPicturesSourcePath($path)
	{
		$this->picturesSourcePath = $path;
	}

	public function setRemoteWebsiteRoot($path)
	{
		$this->remoteWebsiteRoot = $path;
	}

	public function setRsyncLogFile($filename)
	{
		$this->rsyncLogFile = $filename;
	}

	public function setAlbumsPath($path)
	{
		$this->albumsPath = $path;
	}

	public function setQueuePath($path)
	{
		$this->queuePath = $path;
	}

	private function getNextFile()
	{
		$files = array();

		$dir = scandir($this->queuePath);
		foreach ($dir as $file)
		{
			if ($file[0] != "." and is_file($this->queuePath . "/" . $file) and pathinfo($file, PATHINFO_EXTENSION) != "disabled")
			{
				$fileData = new StdClass;
				$fileData->name = $file;
				$fileData->time = filemtime($this->queuePath . "/" . $file);

				$files[] = $fileData;
			}
		}

		if (empty($files))
		{
			return null;
		}

		usort($files, function($item1, $item2)
		{
			if ($item1->time == $item2->time)
			{
				return 0;
			}

			return $item1->time > $item2->time ? 1 : -1;
		});

		return $files[0]->name;
	}

	public function run()
	{
		$loopCount = 0;

		while (($queueFile = $this->getNextFile()) != null)
		{
			$queueFile = $this->queuePath . "/" . $queueFile;

			$queueItem = new QueueItem($queueFile, $this->picturesSourcePath, $this->albumsPath, $this->remoteWebsiteRoot, $this->rsyncLogFile, $this->sshServer);

			if ($queueItem->run())
			{
				unlink($queueFile);
			}
			else
			{
				rename($queueFile, $queueFile . ".disabled");
			}

			$loopCount++;

			if ($loopCount >= $this->maxLoopCount)
			{
				sleep(300);
				$loopCount = 0;
			}
		}
	}
}
