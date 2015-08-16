#! /usr/bin/env php
<?php
use com\selfcoders\mvotools\pictureuploader\queue\Queue;

require_once __DIR__ . "/../bootstrap.php";

$lockFileHandle = fopen(LOCK_FILE, "w+");
if (!flock($lockFileHandle, LOCK_EX | LOCK_NB))
{
	fclose($lockFileHandle);
	exit;
}

$queue = new Queue;

$queue->process();

fclose($lockFileHandle);
unlink(LOCK_FILE);