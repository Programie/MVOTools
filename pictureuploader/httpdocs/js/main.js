$(function()
{
	loadData(
	{
		endpoint : "years",
		callback : function(data, success)
		{
			if (!success)
			{
				return;
			}

			data.sort(function(item1, item2)
			{
				return item2 - item1;
			});

			$("#years").html(Mustache.render($("#years-template").html(), data));
		}
	});

	var yearsContainer = $("#years");

	yearsContainer.on("show.bs.collapse", ".panel", function()
	{
		loadAlbums($(this).data("year"));
	});

	yearsContainer.on("click", ".upload-button", function()
	{
		$("#upload-confirm-year").text($(this).closest(".panel").data("year"));
		$("#upload-confirm-album").text($(this).closest("tr").data("album"));

		$("#upload-confirm-modal").modal("show");
	});

	$("#upload-confirm-button").on("click", function()
	{
		var year = $("#upload-confirm-year").text();
		var album = $("#upload-confirm-album").text();

		loadData(
		{
			endpoint : "years/" + year + "/albums/" + album,
			method : "PUT",
			callback : function(data, success)
			{
				loadAlbums(year);

				if (success)
				{
					notify("success", "Album zur Warteschlange hinzugef\u00fcgt", "Das Album wird nun hochgeladen.");
				}

				$("#upload-confirm-modal").modal("hide");
			}
		});
	});
});

function loadAlbums(year)
{
	var container = $("#year-" + year).find("tbody");

	loadData(
	{
		endpoint : "years/" + year + "/albums",
		callback : function(data, success)
		{
			if (!success)
			{
				return;
			}

			data.sort(function(item1, item2)
			{
				if (item1.album < item2.album)
				{
					return 1;
				}

				if (item1.album > item2.album)
				{
					return -1;
				}

				return 0;
			});

			for (var index = 0; index < data.length; index++)
			{
				var album = data[index];

				var progress = album.state.current + " von " + album.state.total;

				switch (album.state.state)
				{
					case null:
						album.state.class = "default";
						album.state.title = "Neu";
						break;
					case "queued":
						album.state.class = "warning";
						album.state.title = "In der Warteschlange";
						break;
					case "resize":
						album.state.class = "info";
						album.state.title = "Bilder verkleinern (" + progress + ")";
						break;
					case "cleanup":
						album.state.class = "info";
						album.state.title = "Aufr\u0034umen";
						break;
					case "upload":
						album.state.class = "info";
						album.state.title = "Hochladen (" + progress + ")";
						break;
					case "update_database":
						album.state.class = "info";
						album.state.title = "Datenbank aktualisieren";
						break;
					case "done":
						album.state.class = "success";
						album.state.title = "Fertig";
						break;
					case "error":
						album.state.class = "danger";
						album.state.title = "Fehler";
						break;
				}
			}

			container.html(Mustache.render($("#albums-template").html(), data));
		}
	});
}

function loadData(options)
{
	$.ajax(
	{
		cache : false,
		error : function(jqXhr)
		{
			notify("danger", "Error while loading data", jqXhr.responseText);

			if (options.callback)
			{
				options.callback.call(this, null, false, jqXhr);
			}
		},
		type : options.method ? options.method : "GET",
		success : function(responseData, status, jqXhr)
		{
			if (options.callback)
			{
				options.callback.call(this, responseData, true, jqXhr);
			}
		},
		url : "index.php/" + options.endpoint
	});
}

function notify(type, title, message)
{
	var icon;

	switch (type)
	{
		case "success":
			icon = "ok-sign";
			break;
		case "info":
			icon = "info-sign";
			break;
		case "warning":
			icon = "warning-sign";
			break;
		case "danger":
			icon = "remove-sign";
			break;
	}

	$.notify(
	{
		icon: "glyphicon glyphicon-" + icon,
		title : " <strong>" + title + "</strong><br/>",
		message : message
	},
	{
		type : type,
		z_index : 10000
	});
}