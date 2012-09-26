<?php

/**
 * User
 *
 * @since 1.0
 */

class WPEP_User {
	var $wp_user_id;
	var $ep_user_id;

	public function __construct( $args = array() ) {
		$defaults = array(
			'wp_user_id' => 0,
			'ep_user_id' => 0,
		);
		$r = wp_parse_args( $args, $defaults );

		// WP user id always takes precedence
		if ( $r['wp_user_id'] ) {
			$this->wp_user_id = $r['wp_user_id'];
			$this->setup_userdata_from_wp_user_id();
		} else if ( $r['ep_user_id'] ) {
			$this->ep_user_id = $r['ep_user_id'];
			$this->setup_userdata_from_ep_user_id();
		}
	}

	/**
	 * Given WP user id, set up the user
	 *
	 * We store this locally for efficiency's sake. When not found, query
	 * the EP instance.
	 *
	 * @todo Need to create the function that goes in the other direction
	 */
	protected function setup_userdata_from_wp_user_id() {
		$this->ep_user_id = get_user_meta( $this->wp_user_id, 'ep_user_id', true );

		if ( ! $this->ep_user_id ) {
			$this->ep_user_id = self::create_ep_user( $this->wp_user_id );
		}
	}

	/**
	 * Once we have an EP user id, get the corresponding group id
	 */
	protected function setup_user_group_id() {
		$this->ep_user_group_id = get_user_meta( $this->wp_user_id, 'ep_user_group_id', true );

		if ( ! $this->ep_user_group_id ) {
			$this->ep_user_group_id = self::create_ep_user_group( $this->ep_user_id );
		}
	}

	/////////////////////
	// STATIC METHODS  //
	/////////////////////

	/**
	 * Given a WP user ID, create a new EP user
	 *
	 * @param int $wp_user_id
	 * @return string $ep_user_id
	 */
	public static function create_ep_user( $wp_user_id ) {
		$ep_user_id = '';

		// Use display_name for the WP user
		$wp_user = new WP_User( $wp_user_id );

		if ( is_a( $wp_user, 'WP_User' ) ) {

			try {
				$ep_user = wpep_client()->createAuthorIfNotExistsFor( $wp_user->ID, $wp_user->display_name );
				$ep_user_id = $ep_user->authorID;
				update_user_meta( $wp_user_id, 'ep_user_id', $ep_user_id );
				return $ep_user_id;
			} catch ( Exception $e ) {
				return new WP_Error( 'create_ep_user', __( 'Could not create the Etherpad Lite user.', 'wpep' ) );
			}
		}

	}

}
