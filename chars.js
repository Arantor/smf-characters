function chars_ajax_getSignaturePreview (showPreview)
{
	showPreview = (typeof showPreview == 'undefined') ? false : showPreview;
	console.log(showPreview);

	// Is the error box already visible?
	var errorbox_visible = $("#profile_error").is(":visible");

	$.ajax({
		type: "POST",
		url: smf_scripturl + "?action=xmlhttp;sa=previews;xml",
		data: {item: "sig_preview", signature: $("#char_signature").data("sceditor").getText(), user: $('input[name="u"]').attr("value")},
		context: document.body,
		success: function(request){
			if (showPreview)
			{
				$('#sig_preview, #sig_preview_parsed').show();
				$('#sig_preview_parsed').html($(request).find('[type="preview"]').text() + '<dl></dl>');
			}

			if ($(request).find("error").text() != '')
			{
				// If the box isn't already visible...
				// 1. Add the initial HTML
				// 2. Make it visible
				if (!errorbox_visible)
				{
					// Build our HTML...
					var errors_html = '<span>' + $(request).find('[type="errors_occurred"]').text() + '</span><ul id="list_errors"></ul>';

					// Add it to the box...
					$("#profile_error").html(errors_html);

					// Make it visible
					$("#profile_error").css({display: ""});
				}
				else
				{
					// Remove any existing signature-related errors...
					$("#list_errors").remove(".sig_error");
				}

				var errors = $(request).find('[type="error"]');
				var errors_list = '';

				for (var i = 0; i < errors.length; i++)
					errors_list += '<li class="sig_error">' + $(errors).text() + '</li>';

				$("#list_errors").html(errors_list);
			}
			// If there were more errors besides signature-related ones, don't hide it
			else
			{
				// Remove any signature errors first...
				$("#list_errors").remove(".sig_error");

				// If it still has content, there are other non-signature errors...
				if (!$("#list_errors").has("li"))
				{
					$("#profile_error").css({display:"none"});
					$("#profile_error").html('');
				}
			}
		return false;
		},
	});
	return false;
}