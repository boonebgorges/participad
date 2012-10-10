jQuery(document).ready( function($) {
	var post_id = $('#participad-frontend-post-id').val(); 
	var wpnonce = $('#participad-frontend-nonce').val(); 

	autosavePeriodical = $.schedule({
		time: Participad_Notepad.autosave_interval * 1000, 
		func: function() { 
			participad_frontend_save( post_id, wpnonce );
		}, 
		repeat: true, 
		protect: true
	});
},(jQuery));

