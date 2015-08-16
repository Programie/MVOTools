<?php
namespace com\selfcoders\mvotools\pictureuploader\queue;

class Queue
{
	private $queue;

	private function load()
	{
		$this->queue = array();

		foreach (scandir(QUEUE_PATH) as $year)
		{
			if (!is_dir(QUEUE_PATH . "/" . $year))
			{
				continue;
			}

			if ($year[0] == ".")
			{
				continue;
			}

			if (!is_numeric($year))
			{
				continue;
			}

			foreach (scandir(QUEUE_PATH . "/" . $year) as $album)
			{
				if (!is_file(QUEUE_PATH . "/" . $year . "/" . $album))
				{
					continue;
				}

				if ($album[0] == ".")
				{
					continue;
				}

				$this->queue[] = new Item($year, pathinfo($album, PATHINFO_FILENAME));
			}
		}
	}

	private function processNextItem()
	{
		/**
		 * @var $item Item
		 */
		$item = $this->queue[0];

		$item->process();
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