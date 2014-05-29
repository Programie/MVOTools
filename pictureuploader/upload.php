<?php
require_once __DIR__ . "/includes/config.inc.php";
require_once __DIR__ . "/includes/QueueProcessor.class.php";

if (php_sapi_name() != "cli")
{
	die("This script should only be started from the CLI!");
}

$lockFileHandle = fopen(LOCK_FILE, "w+");
if (!flock($lockFileHandle, LOCK_EX | LOCK_NB))
{
	fclose($lockFileHandle);
	exit;
}

$queueProcessor = new QueueProcessor();

$queueProcessor->setSshServer(SSH_SERVER, SSH_USERNAME, SSH_PRIVATEKEYFILE, SSH_PUBLICKEYFILE);
$queueProcessor->setAlbumsPath(__DIR__ . "/albums");
$queueProcessor->setQueuePath(__DIR__ . "/queue");
$queueProcessor->setRemoteWebsiteRoot(REMOTE_WEBSITE_ROOT);
$queueProcessor->setRsyncLogFile(RSYNC_LOG_FILE);

$queueProcessor->run();

fclose($lockFileHandle);
unlink(LOCK_FILE);