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

	public function getPicturesPath()
	{
		return PICTURES_PATH . "/" . $this->year . "/" . $this->album;
	}

	public function getDataPath()
	{
		return $this->getPicturesPath() . "/" . DATA_FOLDER;
	}

	public function load()
	{
		$this->state = new State;

		$dataPath = $this->getDataPath();

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

		$this->state->state = State::STATE_DONE;

		$this->id = (int) $data->id;
		$this->pictures = $data->pictures;

		$this->state->load($this);

		return true;
	}

	public function save()
	{
		$dataPath = $this->getDataPath();

		if (!is_dir($dataPath))
		{
			mkdir($dataPath, 0775);
		}

		file_put_contents($dataPath . "/album.json", json_encode(array
		(
			"id" => (int) $this->id,
			"pictures" => $this->pictures
		)));
	}
}