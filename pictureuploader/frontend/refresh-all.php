<?php
require_once __DIR__ . "/../includes/config.inc.php";

$rootPath = __DIR__ . "/..";

foreach (scandir($rootPath . "/albums") as $year)
{
	if ($year[0] == ".")
	{
		continue;
	}

	$yearPath = $rootPath . "/albums/" . $year;

	foreach (scandir($yearPath) as $album)
	{
		if ($album[0] == ".")
		{
			continue;
		}

		$file = $yearPath . "/" . $album . "/album.json";

		if (!file_exists($file))
		{
			continue;
		}

		echo "  " . $album . "\n";

		$data = array
		(
			"status" => array
			(
				"status" => "new"
			),
			"year" => $year,
			"folder" => $album
		);

		$queueFile = $rootPath . "/queue/" . md5($file . "/" . time());
		file_put_contents($queueFile, json_encode($data));
	}
}
