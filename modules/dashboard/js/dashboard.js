jQuery(document).ready(function($){
	// Add our tab and remove the others
	var ed_tools = $('#wp-content-editor-tools');
	$(ed_tools).children('a.wp-switch-editor').remove();
	$(ed_tools).prepend('<a id="content-participad" class="hide-if-no-js wp-switch-editor switch-participad">Participad</a>');

	// Change the CSS selector
	$('#wp-content-wrap').removeClass('html-active');
	$('#wp-content-wrap').removeClass('tmce-active');
	$('#wp-content-wrap').addClass('participad-active');

	// Swap out the editor
	var ed_cont = $('#wp-content-editor-container');
	$(ed_cont).children().remove();
	$(ed_cont).append('<iframe src="' + Participad_Editor.url + '"></iframe>');

	if ( Participad_Editor.dummy_post_ID ) {
		$(ed_cont).after('<input type="hidden" name="participad_dummy_post_ID" value="' + Participad_Editor.dummy_post_ID + '">');
	}
},(jQuery));

/**
 * This code is not currently in use. I have to find a way to toss content
 * between tabs in a way that's reliable.
 */
var participad = {
	switch_to_ep: function() {
		var ed, from_mode, wrap_id, textarea_el, dom;

		ed = tinyMCE.get('content');
		dom = tinymce.DOM;
		wrap_id = 'wp-content-wrap';
		textarea_el = dom.get('content');

		if ( ed && !ed.isHidden() ) {
			from_mode = 'tmce';
		} else if ( textarea_el.style.display != 'none' ) {
			from_mode = 'html';
		} else {
			from_mode = 'participad';
		}

		if ( 'participad' != from_mode ) {
			dom.removeClass(wrap_id, from_mode + '-active');
			dom.addClass(wrap_id, 'participad-active');
			setUserSetting('editor', 'participad');

		}

		console.log(from_mode);
	}
}
