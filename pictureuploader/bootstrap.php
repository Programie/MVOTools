<?php
require_once __DIR__ . "/vendor/autoload.php";

define("APP_NAMESPACE", "com\\selfcoders\\mvotools\\pictureuploader");
define("QUEUE_PATH", __DIR__ . "/queue");
define("DATA_FOLDER", ".pictureuploader");

if (file_exists(__DIR__ . "/config/config.php"))
{
	require_once __DIR__ . "/config/config.php";
}
else
{
	die("Not configured");
}