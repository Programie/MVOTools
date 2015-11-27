<?php
namespace com\selfcoders\mvotools\pictureuploader\object;

class State
{
	const STATE_QUEUED = "queued";
	const STATE_RESIZE = "resize";
	const STATE_CLEANUP = "cleanup";
	const STATE_UPLOAD = "upload";
	const STATE_UPDATE_DATABASE = "update_database";
	const STATE_DONE = "done";
	const STATE_ERROR = "error";

	public $state;
	public $current;
	public $total;

	public static function getPath($year, $album)
	{
		return Album::getPath($year, $album) . "/" . DATA_FOLDER . "/state.json";
	}

	public function load(Album $album)
	{
		$file = self::getPath($album->year, $album->album);

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
		Album::createDataFolder($year, $album);

		file_put_contents(self::getPath($year, $album), json_encode(array
		(
			"year" => $year,
			"album" => $album,
			"state" => $state,
			"current" => (int) $current,
			"total" => (int) $total
		)));
	}

	public static function saveError($year, $album, $content)
	{
		Album::createDataFolder($year, $album);

		file_put_contents(self::getPath($year, $album), json_encode(array
		(
			"year" => $year,
			"album" => $album,
			"state" => self::STATE_ERROR,
			"content" => $content
		)));
	}
}