function checkQueryString() {
	var blogUrl = $('#blog_url')[0].value;
	var urlScan = $('#url_scan')[0].value;
	errorMsg = '';
	if (/.*[^\/]$/.exec(blogUrl) && urlScan=='path_info') {
		errorMsg = dotclear.msg.warning_path_info;
	} else if (/.*[^\?]$/.exec(blogUrl) && urlScan=='query_string') {
		errorMsg = dotclear.msg.warning_query_string;
	}
	$("p#urlwarning").remove();
	if (errorMsg != '') {
		$("#blog_url").parents('p').after('<p id="urlwarning" class="warning">'+errorMsg+'</p>');
	}
}


$(function() {
	var blog_url = $('#blog_url');
	if (blog_url.length > 0 && !blog_url.is(':hidden')) {
		checkQueryString();
		$('#blog_url').focusout(checkQueryString);
		$('body').on('change','#url_scan',checkQueryString);
	}

	$('#date_format_select,#time_format_select').change(function() {
		if ($(this).prop('value') == '') {
			return;
		}
		$('#'+$(this).attr('id').replace('_select','')).prop('value', $(this).prop('value'));
		$(this).parent().next('.chosen').html($(this).find(':selected').prop('label'));
	});

	// HTML text editor
	if ($.isFunction(jsToolBar)) {
		$('#blog_desc').each(function() {
			var tbWidgetText = new jsToolBar(this);
			tbWidgetText.draw('xhtml');
		});
	}

});
