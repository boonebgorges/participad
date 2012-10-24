<?php

/**
 * Post class
 *
 * Used primarily for managing link between EP and WP data objects
 */
class Participad_Post {
	var $wp_post;
	var $wp_post_id;
	var $ep_post_id;
	var $ep_post_group_id;
	var $ep_post_id_concat;

	public function __construct( $args = array() ) {
		$defaults = array(
			'wp_post_id' => 0,
			'ep_post_id' => 0,
		);
		$r = wp_parse_args( $args, $defaults );

		// WP post id always takes precedence
		if ( $r['wp_post_id'] ) {
			$this->wp_post_id = $r['wp_post_id'];
			$this->setup_postdata_from_wp_post_id();
		} else if ( $r['ep_post_id'] ) {
			$this->ep_post_id = $r['ep_post_id'];
			$this->setup_postdata_from_ep_post_id();
		}
	}

	/**
	 * Given WP post id, set up the EP post
	 *
	 * We store this locally for efficiency's sake. When not found, query
	 * the EP instance.
	 *
	 * @todo Need to create the function that goes in the other direction
	 */
	protected function setup_postdata_from_wp_post_id() {
		// Set up a group for this post first
		$this->ep_post_group_id = get_post_meta( $this->wp_post_id, 'ep_post_group_id', true );

		if ( ! $this->ep_post_group_id ) {
			$post_group_id = self::create_ep_group( $this->wp_post_id, 'post' );

			if ( ! is_wp_error( $post_group_id ) ) {
				$this->ep_post_group_id = $post_group_id;
				update_post_meta( $this->wp_post_id, 'ep_post_group_id', $this->ep_post_group_id );
			}
		}

		// Now set up the post
		$this->ep_post_id = get_post_meta( $this->wp_post_id, 'ep_post_id', true );

		if ( ! $this->ep_post_id ) {
			$post_id = self::create_ep_post( $this->wp_post_id, $this->ep_post_group_id );

			if ( ! is_wp_error( $post_id ) ) {
				$this->ep_post_id = $post_id;
				update_post_meta( $this->wp_post_id, 'ep_post_id', $this->ep_post_id );
			}
		}

		// We need a concatenated id for API queries
		$this->ep_post_id_concat = $this->ep_post_group_id . '$' . $this->ep_post_id;
	}

	/**
	 * Get the WP post object
	 *
	 * Handled separately because it's not needed for most uses, only at sync
	 */
	function setup_wp_post() {
		if ( $this->wp_post_id ) {
			$wp_post = get_post( $this->wp_post_id );
			if ( ! empty( $wp_post ) && ! is_wp_error( $wp_post ) ) {
				$this->wp_post = $wp_post;
			}
		}
	}

	/**
	 * Create an EP group
	 *
	 * We use a mapper_type prefix to allow for future iterations of this
	 * plugin where there are different kinds of mappers than 'type' (such
	 * as BuddyPress groups)
	 *
	 * @param int $mapper_id The numeric ID of the mapped object (eg post)
	 * @param string $mapper_type Eg 'post'
	 * @return string|object The group id on success, or a WP_Error object
	 *   on failure
	 */
	public static function create_ep_group( $mapper_id, $mapper_type ) {
		$group_mapper = $mapper_type . '_' . $mapper_id;

		try {
			$ep_post_group = participad_client()->createGroupIfNotExistsFor( $group_mapper );
			return $ep_post_group->groupID;
		} catch ( Exception $e ) {
			return new WP_Error( 'create_ep_post_group', __( 'Could not create the Etherpad Lite group.', 'participad' ) );
		}
	}

	public static function create_ep_post( $wp_post_id, $ep_post_group_id ) {

		$ep_post_id  = self::generate_random_name();
		$pad_created = false;

		while ( !$pad_created ) {
			try {
				$wp_post         = get_post( $wp_post_id );
				$wp_post_content = isset( $wp_post->post_content ) ? $wp_post->post_content : '';
				$ep_post         = participad_client()->createGroupPad( $ep_post_group_id, $ep_post_id, $wp_post_content );
				$pad_created     = true;
			} catch ( Exception $e ) {
				$ep_post_id      = self::generate_random_name();
			}
		}

		return $ep_post_id;
	}

	/**
	 * Gets a random ID. Hashed and salted so it can't be easily reverse engineered
	 */
	public static function generate_random_name() {
		return wp_hash( uniqid() );
	}

	/**
	 * Steps:
	 * - Get lastEdited from EP and WP post_modified_gmt
	 * - Get last_synced meta from WP postmeta
	 * - If last_synced does not exist, set to date_created. Then, in the case of new posts, EP
	 *   content will copy over normally. In the case where there have been edits on the WP side,
	 *   reconciliation will proceed as expected
	 * - If both WP and EP last_edited match last_synced, there's nothing to do
	 * - If one of the last_edited matches last_synced, the other should be later. Overwrite the older
	 *   content with the new
	 * - If neither matches last_synced, check to see whether the contents are different. If so, go
	 *   to reconciliation mode
	 *
	 */
	public function sync_wp_ep_content() {
		if ( $this->wp_post_id && $this->ep_post_id_concat ) {

			$last_synced = get_post_meta( $this->wp_post_id, 'ep_last_synced', true );

			if ( $sync_time = get_post_meta( $this->wp_post_id, '_ep_doing_sync', true ) ) {
				// If a sync has been running for more than 10 seconds,
				// assume it's failed
				if ( time() - $sync_time >= 10 ) {
					delete_post_meta( $this->wp_post_id, '_ep_doing_sync' );
				} else {
					// We're mid-sync, so bail
					return false;
				}
			}

			$this->setup_wp_post();

			// Unknown failure looking up post
			if ( ! $this->wp_post ) {
				return false;
			}

			update_post_meta( $this->wp_post_id, '_ep_doing_sync', time() );

			$wp_last_edited = strtotime( $this->wp_post->post_modified_gmt );

			// getLastEdited doesn't exist on older versions of EPL
			//$ep_last_edited = self::get_ep_post_last_edited( $this->ep_post_id_concat );

			// @todo There are issues with the way that EPL's API allows for pad text
			// to be set - stuff like HTML breaks the pad, and ruins user highlighting.
			// For the time being, EP content will never be overwritten. May revisit in
			// the future (see logic below)
			wp_update_post( array(
				'ID'	       => $this->wp_post_id,
				'post_content' => self::get_ep_post_content( $this->ep_post_id_concat ),
			) );

			// It's possible that there will be a second or two lag, which will mean
			// that $ep_last_edited and post_modified_gmt will not match. To make
			// sure this doesn't break the next sync, set ep_last_synced to the
			// post_modified_gmt of the queried post. This way, if there's a mismatch,
			// it'll simply trigger a new sync
			$updated_post    = get_post( $this->wp_post_id );
			$new_last_synced = strtotime( $updated_post->post_modified_gmt );

			/*
			// If there's no last_synced key, set it to the older of the edited dates
			if ( ! $last_synced ) {
				$last_synced = $wp_last_edited > $ep_last_edited ? $ep_last_edited : $wp_last_edited;
			}

			// Both last_edited stamps match last_synced. Nothing to do
			if ( $last_synced == $wp_last_edited && $last_synced == $ep_last_edited ) {
				return true;

			// WP matches, and EP is newer. Sync EP content to WP
			// This is the case with normal syncs
			} else if ( $last_synced == $wp_last_edited && $ep_last_edited > $wp_last_edited ) {
				wp_update_post( array(
					'ID'		    => $this->wp_post_id,
					'post_content'      => self::get_ep_post_content( $this->ep_post_id_concat ),
				) );

				// It's possible that there will be a second or two lag, which will mean
				// that $ep_last_edited and post_modified_gmt will not match. To make
				// sure this doesn't break the next sync, set ep_last_synced to the
				// post_modified_gmt of the queried post. This way, if there's a mismatch,
				// it'll simply trigger a new sync
				$updated_post    = get_post( $this->wp_post_id );
				$new_last_synced = strtotime( $updated_post->post_modified_gmt );

			// EP matches, and WP is newer. Sync WP content to EP
			// This happens when you've made local, non-EP edits to the WP content
			} else if ( $last_synced == $ep_last_edited && $ep_last_edited < $wp_last_edited ) {
				self::set_ep_post_content( $this->ep_post_id_concat, $this->wp_post->post_content );
				$new_last_synced = self::get_ep_post_last_edited( $this->ep_post_id_concat );

			// Any other result means that there's been a mismatch of some sort -
			// there are unsynced EP and WP edits, or a local WP draft has been deleted,
			// or some other unknown issue. Send to manual mode
			} else {
				// @todo
			}
			*/

			if ( isset( $new_last_synced ) ) {
				update_post_meta( $this->wp_post_id, 'ep_last_synced', $new_last_synced );
			}

			delete_post_meta( $this->wp_post_id, '_ep_doing_sync' );
		}
	}

	/**
	 * Create an EP group session
	 *
	 * @param string Etherpad group id
	 * @param string Etherpad user id
	 * @return string|object The session id on success, or a WP_Error
	 *   object on failure
	 */
	public static function create_ep_group_session( $ep_group_id, $ep_user_id ) {
		try {
			// @todo Do we need shorter expirations?
			$expiration  = time() + ( 60 * 60 * 24 * 365 * 100 );
			$ep_session  = participad_client()->createSession( $ep_group_id, $ep_user_id, $expiration );
			return $ep_session->sessionID;
		} catch ( Exception $e ) {
			return new WP_Error( 'create_ep_group_session', __( 'Could not create the Etherpad Lite session.', 'participad' ) );
		}
	}

	/**
	 * Get the last edited date for a post, and return in standard UNIX format (minus microseconds)
	 */
	public static function get_ep_post_last_edited( $ep_post_id ) {
		try {
			$last_edited = participad_client()->getLastEdited( $ep_post_id );

			// WP doesn't keep track of microseconds, so we have to strip them
			return (int) substr( $last_edited->lastEdited, 0, -3 );
		} catch ( Exception $e ) {
			return new WP_Error( 'get_ep_post_last_edited', __( 'Could not get the last edited date of this Etherpad Lite post', 'participad' ) );
		}
	}

	public static function get_ep_post_content( $ep_post_id ) {
		try {
			$content = participad_client()->getHTML( $ep_post_id );
			return $content->html;
		} catch ( Exception $e ) {
			return new WP_Error( 'get_ep_post_last_edited', __( 'Could not get the last edited date of this Etherpad Lite post', 'participad' ) );
		}
	}

	public static function set_ep_post_content( $ep_post_id, $post_content ) {
		try {
			$content = participad_client()->setText( $ep_post_id, $post_content );
			return $content->message;
		} catch ( Exception $e ) {
			return new WP_Error( 'get_ep_post_last_edited', __( 'Could not get the last edited date of this Etherpad Lite post', 'participad' ) );
		}
	}
}
