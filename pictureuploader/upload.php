<?php
require_once __DIR__ . "/includes/config.inc.php";
require_once __DIR__ . "/includes/QueueProcessor.class.php";
require_once __DIR__ . "/includes/SshServer.class.php";

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

$sshServer = new SshServer();
$sshServer->server = SSH_SERVER;
$sshServer->username = SSH_USERNAME;
$sshServer->privateKeyFile = SSH_PRIVATEKEYFILE;
$sshServer->publicKeyFile = SSH_PUBLICKEYFILE;

$queueProcessor = new QueueProcessor();

$queueProcessor->setSshServer($sshServer);
$queueProcessor->setAlbumsPath(__DIR__ . "/albums");
$queueProcessor->setQueuePath(__DIR__ . "/queue");
$queueProcessor->setPicturesSourcePath(PICTURES_SOURCE);
$queueProcessor->setRemoteWebsiteRoot(REMOTE_WEBSITE_ROOT);
$queueProcessor->setRsyncLogFile(RSYNC_LOG_FILE);

$queueProcessor->run();

fclose($lockFileHandle);
unlink(LOCK_FILE);