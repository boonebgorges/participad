jQuery(document).ready( function($) {
	var post_id = $('#participad-frontend-post-id').val(); 
	var wpnonce = $('#participad-frontend-nonce').val(); 

	$(window).bind('beforeunload', function() { 
		participad_frontend_save( post_id, wpnonce );
	});

	$('body').on('click', 'a', function(e) {
		e.preventDefault();
		var goto_href = this.href;
		participad_frontend_save( post_id, wpnonce, function() { window.location = goto_href; } );
	});
}, (jQuery));

function participad_frontend_save( post_id, wpnonce, callback ) {
	if ( ! callback ) {
		callback = function() { return; }
	}

	jQuery.ajax({
		type: 'POST',
		url: Participad_Frontend.ajaxurl,
		data: {
			action: 'participad_frontend_save',
			_wpnonce: wpnonce,
			post_id: post_id
		},
		success: function(r) {
			callback();
		}
	});
}
