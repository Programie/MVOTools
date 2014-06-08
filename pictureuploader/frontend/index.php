<?php
require_once __DIR__ . "/../includes/config.inc.php";
require_once __DIR__ . "/../includes/QueueItem.class.php";
?>
<!DOCTYPE html>

<html>
	<head>
		<title>MVO Picture Uploader</title>

		<style type="text/css">
			body
			{
				font-family: Verdana, Helvetica, sans-serif;
			}

			table
			{
				border-spacing: 0;
			}

			table td
			{
				border-top: 1px solid #ccc;
			}

			table th, table td
			{
				padding: 5px;
				border-left: 1px solid #ccc;
			}

			table th:first-child, table td:first-child
			{
				border-left: 0;
			}
		</style>

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

				$queueFile = __DIR__ . "/../queue/" . md5($path . "/" . time());

				$statusData = new StdClass;
				$statusData->status = QueueItem::STATUS_NEW;

				$queueData = new StdClass;
				$queueData->status = $statusData;
				$queueData->year = $_GET["year"];
				$queueData->folder = $_GET["folder"];

				file_put_contents($queueFile, json_encode($queueData));

				?>
				<script type="text/javascript">
					alert("Das Album wurde erfolgreich in die Warteschlage aufgenommen und wird nun bearbeitet.");
					document.location = "?year=<?php echo $_GET["year"];?>";
				</script>
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

		<h1>Aktuelle Warteschlange</h1>

		<table>
			<thead>
				<tr>
					<th>Jahr</th>
					<th>Ordner</th>
					<th>Status</th>
					<th>Statusdetails</th>
				</tr>
			</thead>
			<tbody>
				<?php
				$found = false;
				$queuePath = __DIR__ . "/../queue";
				$dir = scandir($queuePath);
				foreach ($dir as $file)
				{
					if ($file[0] != "." and is_file($queuePath . "/" . $file))
					{
						$queueData = json_decode(file_get_contents($queuePath . "/" . $file));

						if ($queueData == null)
						{
							continue;
						}

						$statusData = $queueData->status;

						$statusDetails = $statusData->data;

						switch ($statusData->status)
						{
							case QueueItem::STATUS_NEW:
								$status = "Neu";
								break;
							case QueueItem::STATUS_ERROR:
								$status = "Fehler";
								break;
							case QueueItem::STATUS_PREPARING:
								$status = "Vorbereiten";
								break;
							case QueueItem::STATUS_CLEANUP:
								$status = "Aufr&auml;umen";
								break;
							case QueueItem::STATUS_RESIZING_IMAGES:
								$status = "Bilder verkleinern";
								$statusDetails = $statusDetails->current . " / " . $statusDetails->total;
								break;
							case QueueItem::STATUS_UPLOADING:
								$status = "Hochladen";
								$statusDetails = ($statusDetails->totalCheck - $statusDetails->toCheck) . " / " . $statusDetails->totalCheck;
								break;
							case QueueItem::STATUS_UPDATING_DATABASE:
								$status = "Datenbank aktualisieren";
								break;
							case QueueItem::STATUS_WRITING_ALBUM_INFO:
								$status = "Albuminformationen schreiben";
								break;
							default:
								$status = $statusData->status;
						}

						echo "
							<tr>
								<td>" . $queueData->year . "</td>
								<td>" . $queueData->folder . "</td>
								<td>" . $status . "</td>
								<td>" . $statusDetails . "</td>
							</tr>
						";

						$found = true;
					}
				}

				if (!$found)
				{
					echo "
						<tr>
							<td colspan='4'>Keine Eintr&auml;ge vorhanden!</td>
						</tr>
					";
				}
				?>
			</tbody>
		</table>
	</body>
</html>