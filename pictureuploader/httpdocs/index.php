<?php
require_once __DIR__ . "/../bootstrap.php";

if (isset($_SERVER["PATH_INFO"]))
{
	$router = new AltoRouter;

	$router->map("GET", "/years", "Pictures#getYears");
	$router->map("GET", "/years/[i:year]/albums", "Pictures#getAlbums");
	$router->map("PUT", "/years/[i:year]/albums/[**:album]", "Pictures#upload");

	$match = $router->match($_SERVER["PATH_INFO"]);

	if ($match === false)
	{
		header("HTTP/1.1 404 Not Found");
		echo "Not found";
		exit;
	}

	list($className, $methodName) = explode("#", $match["target"]);

	$classPath = APP_NAMESPACE . "\\service\\" . $className;

	$instance = new $classPath;

	$response = $instance->$methodName((object) $match["params"]);

	if ($response === null)
	{
		exit;
	}

	$responseString = json_encode($response);

	header("Content-Type: application/json");
	header("Content-Length: " . strlen($responseString));
	echo $responseString;
	exit;
}
?>
<!DOCTYPE html>
<html>
	<head>
		<title>MVO Picture Uploader</title>
	</head>
	<body>
		TODO
	</body>
</html>
