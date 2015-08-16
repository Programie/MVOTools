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

		<link rel="stylesheet" type="text/css" href="bower_components/bootstrap/dist/css/bootstrap.min.css"/>

		<script type="text/javascript" src="bower_components/jquery/dist/jquery.min.js"></script>
		<script type="text/javascript" src="bower_components/bootstrap/dist/js/bootstrap.min.js"></script>

		<script type="text/javascript" src="bower_components/mustache/mustache.min.js"></script>

		<script type="text/javascript" src="js/main.js"></script>

		<script type="text/html" id="years-template">
			{{#.}}
				<div class="panel panel-default" data-year="{{.}}">
					<div class="panel-heading" role="tab">
						<h1 class="panel-title"><a role="button" data-toggle="collapse" data-parent="#years" href="#year-{{.}}">{{.}}</a></h1>
					</div>
					<div class="panel-collapse collapse year-panel" role="tabpanel" id="year-{{.}}">
						<ul class="list-group"></ul>
					</div>
				</div>
			{{/.}}
		</script>

		<script type="text/html" id="albums-template">
			{{#.}}
				<li class="list-group-item list-group-item-{{state.class}}">
					<span class="badge">{{state.state}}</span>
					<h4 class="list-group-item-heading">{{album}}</h4>
					<p class="list-group-item-text"><button class="btn btn-sm btn-primary">Upload</button></p>
				</li>
			{{/.}}
		</script>
	</head>
	<body>
		<div class="container-fluid">
			<nav class="navbar navbar-default">
				<div class="container-fluid">
					<div class="navbar-header">
						<span class="navbar-brand">MVO Picture Uploader</span>
					</div>
				</div>
			</nav>

			<div class="panel-group" id="years" role="tablist"></div>
		</div>
	</body>
</html>
