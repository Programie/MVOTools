<?php
namespace com\selfcoders\mvotools\pictureuploader\service;

use com\selfcoders\mvotools\pictureuploader\object\Album;
use com\selfcoders\mvotools\pictureuploader\object\State;

class Pictures
{
	public function getYears()
	{
		$years = array();

		foreach (scandir(PICTURES_PATH) as $year)
		{
			if (!is_dir(PICTURES_PATH . "/" . $year))
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

			$years[] = (int) $year;
		}

		return $years;
	}

	public function getAlbums($params)
	{
		$year = (int) $params->year;

		$path = PICTURES_PATH . "/" . $year;

		if (!is_dir($path))
		{
			header("HTTP/1.1 404 Not Found");
			return null;
		}

		$albums = array();

		foreach (scandir($path) as $album)
		{
			if (!is_dir($path . "/" . $album))
			{
				continue;
			}

			if ($album[0] == ".")
			{
				continue;
			}

			$albumData = new Album;

			$albumData->year = $year;
			$albumData->album = $album;

			$albumData->load();

			$albums[] = $albumData;
		}

		return $albums;
	}

	public function upload($params)
	{
		$year = (int) $params->year;
		$album = basename($params->album);

		$path = PICTURES_PATH . "/" . $year . "/" . $album;

		if (!is_dir($path))
		{
			header("HTTP/1.1 404 Not Found");
			return null;
		}

		$file = QUEUE_PATH . "/" . $year . "/" . $album . ".json";

		if (file_exists($file))
		{
			header("HTTP/1.1 409 Conflict");
			return null;
		}

		State::save($year, $album, State::STATE_QUEUED);
	}
}