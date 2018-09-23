<?php
	ini_set("display_errors", "Off");

	if (isset($_REQUEST["url"])) {
		include_once("common-inc.php");
		
		$image_url = filter_var($_REQUEST["url"], FILTER_SANITIZE_URL);

		if (!empty($_REQUEST["username"])) {
			// Basic Auth
			// https://stackoverflow.com/questions/30628361/php-basic-auth-file-get-contents
			$auth = base64_encode($_REQUEST["username"] . ":" . $_REQUEST["password"]);
			$context = stream_context_create(array(
				"http" => array(
					"header" => "Authorization: Basic $auth"
				)
			));
		}

		try {
			$image_data = file_get_contents($image_url, false, $context);
			$output["name"] = pathinfo($image_url, PATHINFO_FILENAME);
			$output["type"] = pathinfo($image_url, PATHINFO_EXTENSION);
			$output["status"] = parseHeaders($http_response_header);
			$output["url"] = $image_url;
			
			if ($output["status"]["response_code"] == 200 && strpos($output["status"]["Content-Type"], "image") === 0) {
				$output["data"] = base64_encode($image_data);
			} else {
				switch ($output["status"]["response_code"]) {
					case 200: 
						$output["error"] = "NOT_IMAGE";
						break;
					case 403: 
					case 404: 
					default: 
						$output["error"] = $output["status"][0];
						break;
				}
			}
		} catch (Exception $ex) {
			$output["error"] = $ex->getMessage();
		}
		
		header("Content-Type: application/json");
		echo json_encode($output);
		exit();
	}
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="UTF-8" />
		<title>Image Grabber</title>
		
		<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9gVQ4dYFwwWSjIDZnLEWnxCjeSWFphJiwGPXr1jddIhOegiu1FwO5qRGvFXOdJZ4" crossorigin="anonymous">
		
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js" type="text/javascript"></script>
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js" integrity="sha384-uefMccjFJAIv6A+rW+L4AHf99KvxDjWSu1z9VI8SKNVmz4sk7buKt/6v9KI65qnm" crossorigin="anonymous"></script>
		
		<script>
			var imageGrabber = {
				serviceUrl: window.location, 
				targetUrls: [], 
				authentication: {
					username: null, 
					password: null
				}, 
				cursor: 0, 
				completed: 0, 
				timer: null, 
				interval: 500, 
				addToQueue: function (url, options) {
					if (url instanceof Array) {
						for (var i = 0; i < url.length; i++)
							this.addToQueue(url[i], options);
						return;
					}
					
					options = options || {};
					if (options.jump)
						imageGrabber.targetUrls.splice(imageGrabber.cursor + 1, 0, url);
					else
						imageGrabber.targetUrls.push(url);
				}, 
				executeQueue: function () {
					if (imageGrabber.timer) {
						throw new Error("IMAGEGRABBER_ALREADY_RUNNING");
						return;
					}
					nextInQueue = function () {
						if (imageGrabber.cursor < imageGrabber.targetUrls.length) {
							imageGrabber.fn.getImage(imageGrabber.targetUrls[imageGrabber.cursor]);
							imageGrabber.cursor++;
							imageGrabber.timer = setTimeout(nextInQueue, imageGrabber.interval);
						} else {
							clearTimeout(imageGrabber.timer);
							imageGrabber.timer = null;
							document.dispatchEvent(new CustomEvent(
								"ig-queue-dispatched", 
								{
									detail: {
										message: "Queue dispatched. [Count: " + (imageGrabber.cursor - imageGrabber.completed) + "]"
									}, 
									bubbles: true, 
									cancelable: true
								}
							));
						}
					}
					//imageGrabber.timer = setTimeout(nextInQueue, imageGrabber.interval);
					nextInQueue();
				}, 
				setHTTPAuthentication: function (username, password) {
					imageGrabber.authentication.username = username;
					imageGrabber.authentication.password = password;
				}, 
				fn: {
					getImage: function (url) {
						$.post({
							url: imageGrabber.serviceUrl, 
							data: {
								url: url, 
								username: imageGrabber.authentication.username, 
								password: imageGrabber.authentication.password
							}, 
							dataType: "json"
						})
						.done(function (data) {
							if (!imageGrabber.fn.processImage(data))
								document.dispatchEvent(new CustomEvent(
									"ig-queue-item-error", 
									{
										detail: {
											message: "IMAGE_NOT_PROCESSED", 
											description: ((data.name && data.type) ? ("when retrieving " + data.name + "." + data.type) : "") + (data.url ? (" from " + data.url + ".") : ""), 
											severity: "error"
										}, 
										bubbles: true, 
										cancelable: true
									}
								));
						})
						.fail(function (data) {
							document.dispatchEvent(new CustomEvent(
								"ig-queue-item-error", 
								{
									detail: {
										message: "REQUEST_FAILED", 
										description: url, 
										severity: "error"
									}, 
									bubbles: true, 
									cancelable: true
								}
							));
						})
						.always(function (data) {
							var error = false;
							
							if (!data || data.error)
								error = true;
							
							if (error) {
								document.dispatchEvent(new CustomEvent(
									"ig-queue-item-error", 
									{
										detail: {
											message: "Error " + data.error, 
											description: ((data.name && data.type) ? ("when retrieving " + data.name + "." + data.type) : "") + (data.url ? (" from " + data.url + ".") : ""), 
											severity: "error"
										}, 
										bubbles: true, 
										cancelable: true
									}
								));
							}

							imageGrabber.completed++;
							
							if (imageGrabber.completed >= imageGrabber.targetUrls.length)
								document.dispatchEvent(new CustomEvent(
									"ig-queue-done", 
									{
										detail: {
											message: "Queue done. [Count: " + imageGrabber.cursor + "]"
										}, 
										bubbles: true, 
										cancelable: true
									}
								));
						});
					}, 
					processImage: function (data) {
						if (data && data.type && data.data) {
							var dataUrl = "data:image/" + data.type + ";base64," + data.data, 
								divElement = $("<div />", {
									class: "image-container col-sm-2 col-md-3"
								}), 
								imgElement = $("<img />", {
									src: dataUrl, 
									alt: data.name, 
									download: data.name + "." + data.type
								}).appendTo(divElement);
							
							if ($("#container").append(divElement).length > 0) {
								var container = $("#container");
								
								container.animate({ scrollTop: container.prop("scrollHeight") - container.height() }, "fast");
								
								return true;
							}
						}
						
						return false;
					}
				}
			};
			
			$(function () {
				document.addEventListener("ig-queue-dispatched", function (e) {
					console.log(e.detail.message);
				});
				
				document.addEventListener("ig-queue-done", function (e) {
					//alert(e.detail.message);
					console.log(e.detail.message);
					showMessage(e.detail);
				}, false);

				document.addEventListener("ig-queue-item-error", function (e) {
					showMessage(e.detail);
				});

				$(document).on("click", "img", function () {
					$a = $("<a href=\"" + this.src.replace(/^data:image\/[^;]/, "data:application/octet-stream") + "\" target=\"_blank\" download=\"" + $(this).attr("download") + "\">Download</a>");
					$a.appendTo("#links").get(0).click();
					//location.href = this.src.replace(/^data:image\/[^;]/, "data:application/octet-stream");
				});
			});

			function showMessage(detail) {
				var messageTitle = detail.message, 
					messageBody = detail.description, 
					calloutElement = $("<div />", {
						class: "bs-callout " + (detail.severity === "error" ? "bs-callout-danger" : "bs-callout-info")
					}), 
					titleElement = $("<h4 />", {
						html: messageTitle
					}).appendTo(calloutElement),
					bodyElement = $("<p />", {
						html: messageBody
					}).appendTo(calloutElement);
				
				var container = $("#errors");
				
				container.append(calloutElement).animate({ scrollTop: container.prop("scrollHeight") - container.height() }, "fast");
			}

			function batchDownload() {
				$("img").each(function () {
					$(this).trigger("click");
				});
			}
			
			var sampleQueue = [];
			
			sampleQueue[0] = function () {
				var urlPattern = "https://ia801201.us.archive.org/14/items/Chrysanthemum_20160913/{r}.jpg", 
					filenames = ["Chrysanthemum", "Desert", "Hydrangeas", "Jellyfish", "Koala", "Lighthouse", "Penguins", "Tulips"];
				
				for (var i = 0; i < filenames.length; i++) {
					imageGrabber.addToQueue(urlPattern.replace("{r}", filenames[i]), { jump: true });
				}
				
				imageGrabber.executeQueue();
			};
			
			sampleQueue[1] = function () {
				var urlPattern = "http://via.placeholder.com/{r}x{r}.png", 
					numberOfIterations = 25, 
					minWidth = 100, 
					maxWidth = 300, 
					minHeight = 100, 
					maxHeight = 300;
				
				for (var i = 0; i < numberOfIterations; i++) {
					imageGrabber.addToQueue(urlPattern.replace("{r}", Math.round(Math.random() * (maxWidth - minWidth) + minWidth).toString()).replace("{r}", Math.round(Math.random() * (maxHeight - minHeight) + minHeight).toString()), { jump: true });
				}
				
				imageGrabber.executeQueue();
			};
			
			sampleQueue[2] = function () {
				
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/thumbnails/image/1-bluemarble_west.jpg");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/3iceshelf-alt.gif");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/2-mls_ozone_gases.jpg");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/thumbnails/image/3veg.gif");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/thumbnails/image/2arctic-heating-alt.gif");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/thumbnails/image/no2-sequence.gif");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/thumbnails/image/3burning-alt.gif");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/8-misr_smokeplumes_rimfire2013.jpg");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/thumbnails/image/2seaicemoves-alt.gif");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/thumbnails/image/breathing-alt.gif");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/image01142013_250m.jpg");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/12-aster_gdem-10km-colorized.png");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/12-aster_la.png");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/12-aster_la.png");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/a_phyto_bloom_iceland.jpg");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/14-mopitt_april_2000-2014.png");
				imageGrabber.addToQueue("https://www.nasa.gov/sites/default/files/styles/full_width/public/thumbnails/image/2ozone-alt.gif");
							
				imageGrabber.executeQueue();
			};
		</script>
		<style>
			body {
				width: 100%;
				height: 100%;
				position: fixed;
			}

			img {
				cursor: pointer;
			}
			
			#btn-row {
				height: 2.5%;
			}
			
				#btn-row button {
					padding-top: 0;
					padding-bottom: 0;
				}
			
			#container {
				height: 72.5%;
				overflow: auto;
			}
				
				#container .image-container {
					display: flex;
					justify-content: center;
					align-items: center;
				}
			
				#container img {
					max-width: 100%;
					height: auto;
					display: block;
					margin-left: auto;
					margin-right: auto;
				}
			
			#errors {
				max-height: 25%;
				overflow: auto;
			}
			
			.has-error {
				color: #f00;
			}
			
			/*
			 * Callouts
			 *
			 * Not quite alerts, but custom and helpful notes for folks reading the docs.
			 * Requires a base and modifier class.
			 */

			/* Common styles for all types */
			.bs-callout {
			  padding: 20px;
			  margin: 20px 0;
			  border: 1px solid #eee;
			  border-left-width: 5px;
			  border-radius: 3px;
			}
			.bs-callout h4 {
			  margin-top: 0;
			  margin-bottom: 5px;
			}
			.bs-callout p:last-child {
			  margin-bottom: 0;
			}
			.bs-callout code {
			  border-radius: 3px;
			}

			/* Tighten up space between multiple callouts */
			.bs-callout + .bs-callout {
			  margin-top: -5px;
			}

			/* Variations */
			.bs-callout-danger {
			  border-left-color: #ce4844;
			}
			.bs-callout-danger h4 {
			  color: #ce4844;
			}
			.bs-callout-warning {
			  border-left-color: #aa6708;
			}
			.bs-callout-warning h4 {
			  color: #aa6708;
			}
			.bs-callout-info {
			  border-left-color: #1b809e;
			}
			.bs-callout-info h4 {
			  color: #1b809e;
			}
		</style>
	</head>
	<body class="container-fluid">
		<div id="btn-row" class="row">
			<button class="form-control col-sm-3" onclick="sampleQueue[0]();">Run Sample Queue (Windows 7 Sample Pictures)</button>
			<button class="form-control col-sm-3" onclick="sampleQueue[1]();">Run Sample Queue (Placeholders)</button>
			<button class="form-control col-sm-3" onclick="sampleQueue[2]();">Run Sample Queue (Real pictures)</button>
			<button class="form-control col-md-3" onclick="batchDownload();">Download All Images</button>
		</div>
		<div id="container" class="row">
			
		</div>
		
		<div id="errors" class="container-fluid">
			
		</div>
		<div id="links" style="display: none;">
			
		</div>
	</body>
</html>