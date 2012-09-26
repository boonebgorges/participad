<?php

class WP_Etherpad {

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

	public $ep_post_group_id;

	/**
	 * Use me to bootstrap
	 *
	 * @since 1.0
	 */
	function &init() {
		static $instance = false;

		if ( empty( $instance ) ) {
			$instance = new WP_Etherpad();
		}

		return $instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct(){
		require WP_ETHERPAD_PLUGIN_DIR . 'includes/functions.php';
		require WP_ETHERPAD_PLUGIN_DIR . 'includes/class-wpep-user.php';
		require WP_ETHERPAD_PLUGIN_DIR . 'includes/class-wpep-client.php';

		if ( !$this->load_on_page() ) {
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

		add_action( 'the_editor', array( &$this, 'editor' ), 1 );
	}

	/**
	 * Will an Etherpad instance appear on this page?
	 *
	 * No need to initialize the API client on every pageload
	 *
	 * @todo Implement
	 *
	 * @return bool
	 */
	function load_on_page() {
		$request_uri = $_SERVER['REQUEST_URI'];
		$qpos = strpos( $request_uri, '?' );
		if ( false !== $request_uri ) {
			$request_uri = substr( $request_uri, 0, $qpos );
		}

		return 'post.php' == substr( $request_uri, strrpos( $request_uri, '/' ) + 1 );
	}

	/**
	 * @todo Should this be done with JS instead? Otherwise there's no reliable way to get the content of the editor when creating a new EP
	 */
	function editor( $editor ) {
		static $done_editor;

		// Can only do one per page for a number of reasons
		// @todo Revisit
		if ( ! empty( $done_editor ) ) {
			return $editor;
		}

		echo '<style type="text/css">#wp-content-editor-container iframe { width: 100%; height: 400px; }</style>';

		$ep_url = add_query_arg( array(
			'showControls' => 'true',
			'showChat'     => 'false',
			'showLineNumbers' => 'false',
			'useMonospaceFont' => 'false',
		), WP_ETHERPAD_API_ENDPOINT . '/p/' . $this->ep_post_group_id . '%24' . $this->ep_post_id );

		$editor = preg_replace( '|<textarea.+?/textarea>|', "<iframe src='" . $ep_url . "' height=400></iframe>", $editor );

		$done_editor = 1;

		// We have to bypass the rest of the filters
		echo $editor;
		return '';
	}

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
			$this->loggedin_user  = new WPEP_User( 'wp_user_id=' . $this->wp_user_id );
			$this->ep_user_id = $this->loggedin_user->ep_user_id;
		}
	}

	/**
	 * Will set the current post id if action == edit
	 */
	public function set_wp_post_id(){
		global $post;

		$wp_post_id = 0;

		if ( isset( $_GET['post'] ) ) {
			$wp_post_id = $_GET['post'];
		} else if ( !empty( $post->ID ) ) {
			$wp_post_id = $post->ID;
		}

		$this->wp_post_id = (int) $wp_post_id;
	}

	/**
	 * Look up the EP post id for the current WP post. Generate if necessary
	 */
	public function set_ep_post_id() {
		$ep_post_id = 0;

		if ( $this->wp_post_id ) {
			$ep_post_id = get_post_meta( $this->wp_post_id, 'ep_post_id', true );
		}

		if ( empty( $ep_post_id ) ) {
			$ep_post_id = $this->generate_ep_post_id();

			// Save the newly generated EP post id to the existing WP post
			if ( $this->wp_post_id ) {
				update_post_meta( $this->wp_post_id, 'ep_post_id', $ep_post_id );
			} else {
				// Todo: What do we do when this is an autosave of a newly-created
				// post? Nothing?
			}
		}

		$this->ep_post_id = $ep_post_id;
	}

	/**
	 * Get the post group id
	 *
	 * Etherpad Lite's 'group' model does not map well onto WP's
	 * approximation of ACL. So we create an EP group for each individual
	 * post, and manage sessions dynamically
	 */
	public function set_ep_post_group_id() {
		if ( ! empty( $this->wp_post_id ) ) {
			$this->ep_post_group_id = get_post_meta( $this->wp_post_id, 'ep_post_group_id', true );

			if ( ! $this->ep_post_group_id ) {
				$post_group_id = self::create_ep_group( $this->wp_post_id, 'post' );

				if ( ! is_wp_error( $post_group_id ) ) {
					$this->ep_post_group_id = $post_group_id;
					update_user_meta( $this->wp_post_id, 'ep_post_group_id', $this->ep_post_group_id );
				}
			}
		}
	}

	/**
	 * Get the session between the logged in user and his user group
	 *
	 * Create it if it doesn't exist
	 */
	public function create_session() {
		// Sessions are user-post specific
		$session_key = 'ep_group_session_id-post_' . $this->wp_post_id;

		$this->ep_session_id = get_user_meta( $this->wp_user_id, $session_key, true );

		if ( empty( $this->ep_session_id ) ) {
			$session_id = self::create_ep_group_session( $this->ep_post_group_id, $this->ep_user_id );

			if ( ! is_wp_error( $session_id ) ) {
				$this->ep_session_id = $session_id;
				update_user_meta( $this->wp_user_id, $session_key, $this->ep_session_id );
				setcookie( "sessionID", $this->ep_session_id, time() + ( 60*60 ), "/" );
			}
		}
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
		$ep_post_id = self::generate_random();
		$pad_created = false;

		while ( !$pad_created ) {
			try {
				$wp_post         = get_post( $this->wp_post_id );
				$wp_post_content = isset( $wp_post->post_content ) ? $wp_post->post_content : '';
				$foo             = wpep_client()->createGroupPad( $this->ep_post_group_id, $ep_post_id, $wp_post_content );
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
	public static function generate_random() {
		return wp_hash( uniqid() );
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

?>
