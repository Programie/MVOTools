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

		<link rel="stylesheet" type="text/css" href="css/main.css"/>

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
						<table class="table">
							<thead>
								<tr>
									<th>Album</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			{{/.}}
		</script>

		<script type="text/html" id="albums-template">
			{{#.}}
				<tr data-album="{{album}}">
					<td><span class="upload-button">{{album}}</span></td>
					<td class="alert alert-{{state.class}}">{{state.title}}</td>
				</tr>
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

		<div class="modal fade" id="upload-confirm-modal" tabindex="-1" role="dialog">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title">Album hochladen</h4>
					</div>
					<div class="modal-body">
						Bist du dir sicher, dass du das Album <b id="upload-confirm-album"></b> aus dem Jahr <b id="upload-confirm-year"></b> hochladen m&ouml;chtest?
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-primary" id="upload-confirm-button">Hochladen</button>
						<button type="button" class="btn btn-default" data-dismiss="modal">Abbrechen</button>
					</div>
				</div>
			</div>
		</div>
	</body>
</html>
