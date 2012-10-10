<?php

/**
 * BuddyPress integration for the Participad Notepad module
 *
 * Integrates with the BP Activity component
 *
 * @since 1.0
 */

/**
 * When a Notepad is edited, record the fact to the activity stream
 *
 * @since 1.0
 * @param int $post_id
 * @param object $post
 * @return int The id of the activity item posted
 */
function participad_notepad_record_notepad_activity( $post_id, $post ) {
	global $bp;

	// Run only for participad_notebook post type
	if ( empty( $post->post_type ) || participad_notepad_post_type_name() != $post->post_type ) {
		return;
	}

	// Throttle activity updates: No duplicate posts (same user, same
	// notepad) within 60 minutes
	$already_args = array(
		'max'		=> 1,
		'sort'		=> 'DESC',
		'show_hidden'	=> 1, // We need to compare against all activity
		'filter'	=> array(
			'user_id'	=> get_current_user_id(),
			'action'	=> 'participad_notepad_edited',
			'secondary_id'	=> $post_id // We don't really care about the item_id for these purposes (it could have been changed)
		),
	);

	$already_activity = bp_activity_get( $already_args );

	// If any activity items are found, compare its date_recorded with time() to
	// see if it's within the allotted throttle time. If so, don't record the
	// activity item
	if ( !empty( $already_activity['activities'] ) ) {
		$date_recorded 	= $already_activity['activities'][0]->date_recorded;
		$drunix 	= strtotime( $date_recorded );
		if ( time() - $drunix <= apply_filters( 'participad_notepad_edit_activity_throttle_time', 60*60 ) )
			return;
	}

	$post_permalink = get_permalink( $post_id );
	$action = sprintf( __( '%1$s edited a notepad %2$s on the site %3$s', 'participad' ), bp_core_get_userlink( get_current_user_id() ), '<a href="' . $post_permalink . '">' . esc_html( $post->post_title ) . '</a>', '<a href="' . get_option( 'siteurl' ) . '">' . get_option( 'blogname' ) . '</a>' );

	$activity_id = bp_activity_add( array(
		'user_id'           => get_current_user_id(),
		'component'         => bp_is_active( 'blogs' ) ? $bp->blogs->id : 'blogs',
		'action'            => $action,
		'primary_link'      => $post_permalink,
		'type'              => 'participad_notepad_edited',
		'item_id'           => get_current_blog_id(),
		'secondary_item_id' => $post_id,
		'recorded_time'     => $post->post_modified_gmt,
		'hide_sitewide'     => get_option( 'blog_public' ) <= 0,
	));

	if ( function_exists( 'bp_blogs_update_blogmeta' ) ) {
		bp_blogs_update_blogmeta( get_current_blog_id(), 'last_activity', bp_core_current_time() );
	}

	return $activity_id;
}
add_action( 'save_post', 'participad_notepad_record_notepad_activity', 10, 2 );
