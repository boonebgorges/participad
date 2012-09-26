jQuery(document).ready(function($){
	// Add our tab and remove the others
	var ed_tools = $('#wp-content-editor-tools');
	$(ed_tools).children('a.wp-switch-editor').remove();
	$(ed_tools).prepend('<a id="content-wpep" class="hide-if-no-js wp-switch-editor switch-wpep">Etherpad</a>');

	// Change the CSS selector
	$('#wp-content-wrap').removeClass('html-active');
	$('#wp-content-wrap').removeClass('tmce-active');
	$('#wp-content-wrap').addClass('wpep-active');

	// Swap out the editor
	var ed_cont = $('#wp-content-editor-container');
	$(ed_cont).children().remove();
	$(ed_cont).append('<iframe src="' + WPEP_Editor.url + '"></iframe>');
},(jQuery));

/**
 * This code is not currently in use. I have to find a way to toss content
 * between tabs in a way that's reliable.
 */
var wpep = {
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
			from_mode = 'wpep';
		}

		if ( 'wpep' != from_mode ) {
			dom.removeClass(wrap_id, from_mode + '-active');
			dom.addClass(wrap_id, 'wpep-active');
			setUserSetting('editor', 'wpep');

		}

		console.log(from_mode);
	}
}
