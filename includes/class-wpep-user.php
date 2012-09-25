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
	 */
	protected function setup_userdata_from_wp_user_id() {
		$this->ep_user_id = get_user_meta( $this->wp_user_id, 'wpep_ep_user_id', true );

		if ( ! $this->ep_user_id ) {
			$this->ep_user_id = self::create_ep_user( $this->wp_user_id );
		}
	}

	/**
	 * Given a WP user ID, create a new EP user
	 *
	 * @param int $wp_user_id
	 * @return string $ep_user_id
	 */
	public static function create_ep_user( $wp_user_id ) {
		$ep_user_id = '';

		// Use user_nicename for the WP user
		$wp_user = new WP_User( $wp_user_id );

		if ( is_a( $wp_user, 'WP_User' ) ) {
			$ep_user = wpep_client()->createAuthorIfNotExistsFor( $wp_user->ID, $wp_user->user_nicename );

			if ( isset( $ep_user->authorID ) ) {
				$ep_user_id = $ep_user->authorID;
				update_user_meta( $wp_user_id, 'wpep_ep_user_id', $ep_user_id );
			}
		}

		return $ep_user_id;
	}
}
