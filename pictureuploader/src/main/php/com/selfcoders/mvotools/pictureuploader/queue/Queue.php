<?php
namespace com\selfcoders\mvotools\pictureuploader\queue;

class Queue
{
	private $queue;

	private function load()
	{
		$this->queue = array();

		foreach (scandir(QUEUE_PATH) as $file)
		{
			if ($file[0] == ".")
			{
				continue;
			}

			if (!is_file(QUEUE_PATH . "/" . $file))
			{
				continue;
			}

			$this->queue[] = new Item($file);
		}
	}

	private function processNextItem()
	{
		/**
		 * @var $item Item
		 */
		$item = $this->queue[0];

		if (!$item->process())
		{
			return;
		}

		$item->removeQueueFile();
	}

	public function process()
	{
		while (true)
		{
			$this->load();

			if (empty($this->queue))
			{
				break;
			}

			$this->processNextItem();
		}
	}
}