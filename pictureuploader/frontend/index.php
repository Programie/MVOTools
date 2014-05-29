<?php
require_once __DIR__ . "/../includes/config.inc.php";
?>
<!DOCTYPE html>

<html>
	<head>
		<title>MVO Picture Uploader</title>

		<script type="text/javascript">
			function doConfirm(year, name)
			{
				if (confirm("Soll das Album jetzt hochgeladen werden?\n\nJahr: " + year + "\nAlbum: " + name))
				{
					document.location = "?year=" + year + "&folder=" + name;
				}
			}
		</script>
	</head>

	<body>
	<?php
	$doSave = false;
	$path = PICTURES_SOURCE;
	if (isset($_GET["year"]) and is_numeric($_GET["year"]))
	{
		$path .= "/" . intval($_GET["year"]);
		if (isset($_GET["folder"]))
		{
			$path .= basename($_GET["folder"]);

			echo "<a href='?year=" . $_GET["year"] . "'>Zur&uuml;ck</a>";

			$queueFile = __DIR__ . "/../queue/" . md5($path . "/" . time());

			$queueData = new StdClass;
			$queueData->status = "new";
			$queueData->year = $_GET["year"];
			$queueData->folder = $_GET["folder"];

			file_put_contents($queueFile, json_encode($queueData));

			?>
			<p>Das Album wurde erfolgreich in die Warteschlage aufgenommen und wird nun bearbeitet.</p>
			<?php
		}
		else
		{
			echo "<a href='?'>Zur&uuml;ck</a>";
			?>
			<ul>
				<?php
				$dir = scandir($path);
				foreach ($dir as $item)
				{
					if ($item[0] != "." and is_dir($path . "/" . $item))
					{
						echo "<li style='cursor: pointer;' onclick=\"doConfirm(" . $_GET["year"] .", '" . $item . "');\">" . $item . "</li>";
					}
				}
				?>
			</ul>
			<?php
		}
	}
	else
	{
		?>
		<ul>
			<?php
			$dir = scandir($path);
			sort($dir, SORT_ASC);
			foreach ($dir as $item)
			{
				if ($item[0] != "." and is_dir($path . "/" . $item) and is_numeric($item))
				{
					echo "<li><a href='?year=" . $item . "'>" . $item . "</a></li>";
				}
			}
			?>
		</ul>
		<?php
	}
	?>
	</body>
</html>