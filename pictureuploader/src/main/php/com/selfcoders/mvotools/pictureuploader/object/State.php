<?php
namespace com\selfcoders\mvotools\pictureuploader\object;

class State
{
	const STATE_NONE = "none";
	const STATE_QUEUED = "queued";
	const STATE_RESIZE = "resize";
	const STATE_CLEANUP = "cleanup";
	const STATE_UPLOAD = "upload";
	const STATE_UPDATE_DATABASE = "update_database";
	const STATE_ERROR = "error";

	public $state = self::STATE_NONE;
	public $current;
	public $total;

	public function load(Album $album)
	{
		$path = QUEUE_PATH . "/" . $album->year;

		if (!is_dir($path))
		{
			return false;
		}

		$file = $path . "/" . $album->album . ".json";

		if (!file_exists($file))
		{
			return false;
		}

		$data = json_decode(file_get_contents($file));

		if ($data === null)
		{
			return false;
		}

		$this->state = $data->state;
		$this->current = (int) $data->current;
		$this->total = (int) $data->total;

		return true;
	}

	public static function save($year, $album, $state, $current = null, $total = null)
	{
		$path = QUEUE_PATH . "/" . $year;

		if (!is_dir($path))
		{
			mkdir($path, 0775);
		}

		file_put_contents($path . "/" . $album . ".json", json_encode(array
		(
			"state" => $state,
			"current" => (int) $current,
			"total" => (int) $total
		)));
	}

	public static function saveError($year, $album, $content)
	{
		$path = QUEUE_PATH . "/" . $year;

		if (!is_dir($path))
		{
			mkdir($path, 0775);
		}

		file_put_contents($path . "/" . $album . ".json", json_encode(array
		(
			"state" => self::STATE_ERROR,
			"content" => $content
		)));
	}
}