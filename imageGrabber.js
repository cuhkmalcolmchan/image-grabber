if (!window.imageGrabber) {
	window.imageGrabber = {
		serviceUrl: "image_grabber.php", 
		targetUrls: [], 
		cursor: 0, 
		completed: 0, 
		timer: null, 
		interval: 500, 
		addToQueue: function (url, options) {
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
		fn: {
			getImage: function (url) {
				$.post({
					url: imageGrabber.serviceUrl, 
					data: {
						url: url
					}, 
					dataType: "json"
				})
				.always(function (data) {
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
				})
				.done(function (data) {
					if (!data || data.error) {
						var errorMessageTitle = "Error " + data.error, 
							errorMessageBody = ((data.name && data.type) ? ("when retrieving " + data.name + "." + data.type) : "") + (data.url ? (" from " + data.url + ".") : ""), 
							calloutElement = $("<div />", {
								class: "bs-callout bs-callout-danger"
							}), 
							errorTitleElement = $("<h4 />", {
								html: errorMessageTitle
							}).appendTo(calloutElement),
							errorBodyElement = $("<p />", {
								html: errorMessageBody
							}).appendTo(calloutElement);
						
						console.log(errorMessageTitle + " " + errorMessageBody);
						
						var errorContainer = $("#errors");
						
						errorContainer.append(calloutElement).animate({ scrollTop: errorContainer.prop("scrollHeight") - errorContainer.height() }, "fast");
						throw new Error("IMAGE_BAD_FORMAT");
						return false;
					}
					
					if (!imageGrabber.fn.processImage(data))
						throw new Error("IMAGE_NOT_PROCESSED");
				})
				.fail(function (data) {
					throw new Error("REQUEST_FAILED");
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
							alt: data.name
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
}