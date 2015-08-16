<?php
namespace com\selfcoders\mvotools\pictureuploader\image;

class Resizer
{
	private $originalFilename;
	private $originalImage;
	private $originalWidth;
	private $originalHeight;

	public function __construct($filename)
	{
		$this->originalFilename = $filename;
	}

	public function loadFile($filename)
	{
		$this->loadImage(imagecreatefromjpeg($filename));
	}

	public function loadImage($image)
	{
		$this->originalImage = $image;

		$this->originalWidth = imagesx($image);
		$this->originalHeight = imagesy($image);
	}

	public function resize($newWidth, $newHeight)
	{
		if ($this->originalImage === null)
		{
			$this->loadFile($this->originalFilename);
		}

		$newSize = $this->getSize($newWidth, $newHeight);

		$resizedImage = imagecreatetruecolor($newSize->width, $newSize->height);
		imagecopyresampled($resizedImage, $this->originalImage, 0, 0, 0, 0, $newSize->width, $newSize->height, $this->originalWidth, $this->originalHeight);

		return $resizedImage;
	}

	private function getSizeByFixedHeight($newHeight)
	{
		return $newHeight * ($this->originalWidth / $this->originalHeight);
	}

	private function getSizeByFixedWidth($newWidth)
	{
		return $newWidth * ($this->originalHeight / $this->originalWidth);
	}

	private function getSize($newWidth, $newHeight)
	{
		$size = new Size;

		if ($this->originalHeight < $this->originalWidth)
		{
			$size->width = $newWidth;
			$size->height = $this->getSizeByFixedWidth($newWidth);
		}
		elseif ($this->originalHeight > $this->originalWidth)
		{
			$size->width = $this->getSizeByFixedHeight($newHeight);
			$size->height = $newHeight;
		}
		else
		{
			if ($newHeight < $newWidth)
			{
				$size->width = $newWidth;
				$size->height = $this->getSizeByFixedWidth($newWidth);
			}
			elseif ($newHeight > $newWidth)
			{
				$size->width = $this->getSizeByFixedHeight($newHeight);
				$size->height = $newHeight;
			}
			else
			{
				$size->width = $newWidth;
				$size->height= $newHeight;
			}
		}

		return $size;
	}
}