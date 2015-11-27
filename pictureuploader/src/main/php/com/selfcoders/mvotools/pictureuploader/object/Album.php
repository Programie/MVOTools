<?php
namespace com\selfcoders\mvotools\pictureuploader\object;

class Album
{
	/**
	 * @var int
	 */
	public $id;
	/**
	 * @var int
	 */
	public $year;
	/**
	 * @var string
	 */
	public $album;
	/**
	 * @var State
	 */
	public $state;
	/**
	 * @var array
	 */
	public $pictures;

	public static function getPath($year, $album)
	{
		return PICTURES_PATH . "/" . $year . "/" . $album;
	}

	public static function createDataFolder($year, $album)
	{
		$path = self::getPath($year, $album) . "/" . DATA_FOLDER;

		if (!is_dir($path))
		{
			mkdir($path, 0755, true);
		}
	}

	public function getPicturesPath()
	{
		return PICTURES_PATH . "/" . $this->year . "/" . $this->album;
	}

	public function load()
	{
		$this->state = new State;

		$dataPath = $this->getPicturesPath() . "/" . DATA_FOLDER;

		$file = $dataPath . "/album.json";

		if (!file_exists($file))
		{
			return false;
		}

		$data = json_decode(file_get_contents($file));

		if ($data === null)
		{
			return false;
		}

		$this->id = (int) $data->id;
		$this->pictures = $data->pictures;

		$this->state->load($this);

		return true;
	}

	public function save()
	{
		$dataPath = $this->getPicturesPath() . "/" . DATA_FOLDER;

		if (!is_dir($dataPath))
		{
			mkdir($dataPath, 0775);
		}

		file_put_contents($dataPath . "/album.json", json_encode(array
		(
			"id" => (int) $this->id,
			"year" => (int) $this->year,
			"album" => $this->album,
			"pictures" => $this->pictures
		)));
	}
}
