var post_id, wpnonce;
jQuery(document).ready( function($) {
	// Only parse the DOM once
	var post_id = $('#notepad-post-id').val(); 
	var wpnonce = $('#participad-notepad-nonce').val(); 

	autosavePeriodical = $.schedule({time: Participad_Notepad.autosave_interval * 1000, func: function() { participad_notepad_autosave(post_id,wpnonce); }, repeat: true, protect: true});
},(jQuery));

function participad_notepad_autosave(post_id,wpnonce) {
	jQuery.ajax({
		type: 'POST',
		url: ajaxurl,
		data: {
			action: 'participad_notepad_autosave',
			_wpnonce: wpnonce,
			post_id: post_id
		},
		success: function(r) {}
	});
}
