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

			$("#years").html(Mustache.render($("#years-template").html(), data));
		}
	});

	$("#years").on("show.bs.collapse", ".panel", function()
	{
		var listGroup = $(this).find(".list-group");

		loadData(
		{
			endpoint : "years/" + $(this).data("year") + "/albums",
			callback : function(data, success)
			{
				if (!success)
				{
					return;
				}

				for (var index = 0; index < data.length; index++)
				{
					var album = data[index];

					switch (album.state.state)
					{
						case null:
							album.state.class = "default";
							break;
						case "queued":
							album.state.class = "warning";
							break;
						case "resize":
						case "cleanup":
						case "upload":
						case "update_database":
							album.state.class = "info";
							break;
						case "done":
							album.state.class = "success";
							break;
						case "error":
							album.state.class = "danger";
							break;
					}
				}

				listGroup.html(Mustache.render($("#albums-template").html(), data));
			}
		});
	});
});

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