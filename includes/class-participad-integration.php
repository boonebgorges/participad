<?php

/**
 * Integration class
 *
 * This is the base class for building Participad integration pieces.
 *
 * Extend this class to build your own interface
 */
abstract class Participad_Integration {

	/**
	 * @var obj A Participad_User object representing the current user
	 *
	 * @since 1.0
	 */
	public $loggedin_user;

	/**
	 * @var int ID of the current WP user
	 *
	 * @since 1.0
	 */
	public $wp_user_id;

	/**
	 * @var int ID of the EP user corresponding to the current WP user
	 *
	 * @since 1.0
	 */
	public $ep_user_id;

	/**
	 * @var int ID of the current WP post (empty in the case of new posts)
	 *
	 * @since 1.0
	 */
	public $wp_post_id;

	/**
	 * @var int ID of the current EP post (empty in the case of new posts)
	 *
	 * @since 1.0
	 */
	public $ep_post_id;

	/**
	 * @var int ID of the current EP post group (empty in the case of new posts)
	 *
	 * @since 1.0
	 */
	public $ep_post_group_id;

	/**
	 * Steps through the process of setting up basic integration data
	 *
	 * Be sure to call this method early in your module class's constructor
	 *
	 * @since 1.0
	 */
	public function init() {
		if ( ! $this->load_on_page() ) {
			return;
		}

		/**
		 * Top-level overview:
		 * 1) Figure out the current WP user
		 * 2) Translate that into an EP user
		 * 3) Figure out the current WP post ID
		 * 4) Make sure we've got an EP group corresponding to the WP post
		 * 5) Based on the EP user and group IDs, create a session
		 * 6) Attempt connecting to the EP post with the session
		 */
		$this->set_wp_user_id();
		$this->set_ep_user_id();
		$this->set_wp_post_id();
		$this->set_ep_post_group_id();
		$this->create_session();
		$this->set_ep_post_id();
	}

	/**
	 * Will an Etherpad instance appear on this page?
	 *
	 * No need to initialize the API client on every pageload
	 *
	 * Must be overridden in a module class
	 *
	 * @return bool
	 */
	abstract public function load_on_page();

	/**
	 * Set the current user WP user id property
	 *
	 * @since 1.0
	 * @param bool|int $user_id If false, falls back on logged in user
	 */
	public function set_wp_user_id( $wp_user_id = false ) {
		if ( false === $wp_user_id ) {
			$wp_user_id = get_current_user_id();
		}

		$this->wp_user_id = (int) $wp_user_id;
	}

	/**
	 * Get the EP user id for a given WP user ID
	 *
	 * @since 1.0
	 */
	public function set_ep_user_id() {
		if ( ! empty( $this->wp_user_id ) ) {
			$this->loggedin_user  = new Participad_User( 'wp_user_id=' . $this->wp_user_id );
			$this->ep_user_id = $this->loggedin_user->ep_user_id;
		}
	}

	/**
	 * Set the numeric ID of the current WP post
	 *
	 * There's no generic way to make this work. Your module must provide
	 * an override for this method.
	 */
	abstract public function set_wp_post_id();

	/**
	 * Get the post group id
	 *
	 * Etherpad Lite's 'group' model does not map well onto WP's
	 * approximation of ACL. So we create an EP group for each individual
	 * post, and manage sessions dynamically
	 */
	public function set_ep_post_group_id() {
		if ( ! empty( $this->wp_post_id ) ) {
			$this->current_post     = new Participad_Post( 'wp_post_id=' . $this->wp_post_id );
			$this->ep_post_group_id = $this->current_post->ep_post_group_id;
		}
	}

	/**
	 * Look up the EP post id for the current WP post
	 */
	public function set_ep_post_id() {
		if ( ! empty( $this->current_post ) ) {
			$this->ep_post_id = $this->current_post->ep_post_id;
			$this->ep_post_id_concat = $this->ep_post_group_id . '$' . $this->ep_post_id;
		}
	}

}

?>
