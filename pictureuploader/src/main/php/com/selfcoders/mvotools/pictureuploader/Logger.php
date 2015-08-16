<?php
namespace com\selfcoders\mvotools\pictureuploader;

class Logger
{
	public static function log($string)
	{
		if (php_sapi_name() == "cli")
		{
			echo sprintf("[%s] %s\n", date("r"), $string);
		}
	}
}