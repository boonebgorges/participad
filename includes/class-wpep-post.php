<?php

/**
 * Post class
 *
 * Used primarily for managing link between EP and WP data objects
 */
class WPEP_Post {
	var $wp_post_id;
	var $ep_post_id;
	var $ep_post_group_id;

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
			$ep_post_group = wpep_client()->createGroupIfNotExistsFor( $group_mapper );
			return $ep_post_group->groupID;
		} catch ( Exception $e ) {
			return new WP_Error( 'create_ep_post_group', __( 'Could not create the Etherpad Lite group.', 'wpep' ) );
		}
	}

	public static function create_ep_post( $wp_post_id, $ep_post_group_id ) {

		$ep_post_id  = self::generate_random_name();
		$pad_created = false;

		while ( !$pad_created ) {
			try {
				$wp_post         = get_post( $wp_post_id );
				$wp_post_content = isset( $wp_post->post_content ) ? $wp_post->post_content : '';
				$ep_post         = wpep_client()->createGroupPad( $ep_post_group_id, $ep_post_id, $wp_post_content );
				$pad_created     = true;
			} catch ( Exception $e ) {
				$ep_post_id      = self::generate_random();
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
	 * Generates a unique EP post id
	 *
	 * Uses a random number generator to create an ID, then checks it against EP to see if a
	 * pad exists by that name.
	 *
	 * @return str
	 */
	public function generate_ep_post_id() {
		if ( ! $this->wp_post_id || ! $this->ep_post_group_id ) {
			return;
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
			$ep_session  = wpep_client()->createSession( $ep_group_id, $ep_user_id, $expiration );
			return $ep_session->sessionID;
		} catch ( Exception $e ) {
			return new WP_Error( 'create_ep_group_session', __( 'Could not create the Etherpad Lite session.', 'wpep' ) );
		}
	}


}
