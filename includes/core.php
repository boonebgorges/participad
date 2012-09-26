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

	/**
	 * @var int ID of the current EP post group (empty in the case of new posts)
	 *
	 * @since 1.0
	 */
	public $ep_post_group_id;

	/**
	 * @var The strings that will be passed to a javascript object
	 *
	 * @since 1.0
	 */
	public $localize_script = array();

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
		require WP_ETHERPAD_PLUGIN_DIR . 'includes/class-wpep-post.php';
		require WP_ETHERPAD_PLUGIN_DIR . 'includes/class-wpep-client.php';

		if ( ! $this->load_on_page() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

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

		// Todo: Move this somewhere else?
		if ( $this->ep_post_id ) {
			add_action( 'get_post_metadata', array( $this, 'prevent_check_edit_lock' ), 10, 4 );
			add_action( 'admin_enqueue_scripts', array( $this, 'disable_autosave' ) );
			add_filter( 'wp_insert_post_data', array( $this, 'sync_etherpad_content_to_wp' ), 10, 2 );
			add_filter( 'wp_insert_post', array( $this, 'catch_dummy_post' ), 10, 2 );
		}

	//	add_action( 'the_editor', array( &$this, 'editor' ), 1 );
	}

	/**
	 * Will an Etherpad instance appear on this page?
	 *
	 * No need to initialize the API client on every pageload
	 *
	 * @todo Do a better job of implementing this
	 *
	 * @return bool
	 */
	function load_on_page() {
		$request_uri = $_SERVER['REQUEST_URI'];
		$qpos = strpos( $request_uri, '?' );
		if ( false !== $qpos ) {
			$request_uri = substr( $request_uri, 0, $qpos );
		}

		$retval = false;
		$filename = substr( $request_uri, strrpos( $request_uri, '/' ) + 1 );

		if ( 'post.php' == $filename || 'post-new.php' == $filename ) {
			$retval = true;
		}

		return $retval;
	}

	function enqueue_scripts() {
		wp_enqueue_style( 'wpep_editor', WP_PLUGIN_URL . '/etherpad-wordpress/assets/css/editor.css' );
		wp_enqueue_script( 'wpep_editor', WP_PLUGIN_URL . '/etherpad-wordpress/assets/js/editor.js', array( 'jquery', 'editor' ) );

		$ep_url = add_query_arg( array(
			'showControls' => 'true',
			'showChat'     => 'false',
			'showLineNumbers' => 'false',
			'useMonospaceFont' => 'false',
		), WP_ETHERPAD_API_ENDPOINT . '/p/' . $this->ep_post_group_id . '%24' . $this->ep_post_id );

		$this->localize_script['url'] = $ep_url;
		wp_localize_script( 'wpep_editor', 'WPEP_Editor', $this->localize_script );
	}

	/**
	 * @todo Not currently used. I'm swapping it in with Javascript
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

		echo '<iframe src="' . $ep_url . '" height=400></iframe>';
		die();
//		$editor = preg_replace( '|<textarea.+?/textarea>|', "<iframe src='" . $ep_url . "' height=400></iframe>", $editor );

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
		} else if ( isset( $_POST['post_ID'] ) ) {        // saving post
			$wp_post_id = $_POST['post_ID'];
		} else if ( !empty( $post->ID ) ) {
			$wp_post_id = $post->ID;
		}

		// If we still have no post ID, we're probably in the post
		// creation process. We have to get weird.
		// 1) Create a dummy post for use throughout the process
		// 2) Dynamically add a field to the post creation page that
		//    contains the id of the dummy post
		// 3) When the post is finally created, hook in, look for the
		//    dummy post data, copy it to the new post, and delete the
		//    dummy
		if ( ! $wp_post_id ) {
			$wp_post_id = wp_insert_post( array(
				'post_title'   => 'WPEP_Dummy_Post',
				'post_content' => '',
				'post_status'  => 'auto-draft'
			) );

			$this->localize_script['dummy_post_ID'] = $wp_post_id;
		}

		$this->wp_post_id = (int) $wp_post_id;
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
			$this->current_post     = new WPEP_Post( 'wp_post_id=' . $this->wp_post_id );
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

	/**
	 * Get the session between the logged in user and the current post group
	 *
	 * Create it if it doesn't exist
	 */
	public function create_session() {
		// Sessions are user-post specific
		$session_key = 'ep_group_session_id-post_' . $this->wp_post_id;

		$this->ep_session_id = get_user_meta( $this->wp_user_id, $session_key, true );

		if ( empty( $this->ep_session_id ) ) {
			$session_id = WPEP_Post::create_ep_group_session( $this->ep_post_group_id, $this->ep_user_id );

			if ( ! is_wp_error( $session_id ) ) {
				$this->ep_session_id = $session_id;
				update_user_meta( $this->wp_user_id, $session_key, $this->ep_session_id );
			}
		}

		if ( ! empty( $this->ep_session_id ) ) {
			setcookie( "sessionID", $this->ep_session_id, time() + ( 60*60*24*365*100 ), "/" );
		}
	}

	/**
	 * Prevents setting WP's _edit_lock when on an Etherpad page
	 *
	 * Works a little funky because of the filters available in WP.
	 * Summary:
	 * 1) Filter get_post_metadata
	 * 2) If the key is '_edit_lock', and if the $object_id is the
	 *    current post, return an empty value
	 * 3) The "empty value" must be cast as an array if $single is false
	 * 4) When get_metadata() sees the empty string come back, it returns
	 *    it, thus tricking the checker into thinking that there's no lock
	 * 5) A side effect is that edit locks can't be set either, because
	 *    this filter kills the duplicate check in update_metadata()
	 */
	public function prevent_check_edit_lock( $retval, $object_id, $meta_key, $single ) {
		if ( '_edit_lock' == $meta_key && ! empty( $this->wp_post_id ) && $this->wp_post_id == $object_id ) {
			$retval = $single ? '' : array( '' );
		}

		return $retval;
	}

	/**
	 * Dequeues WP's autosave script, thereby disabling the feature
	 *
	 * No need for autosave here
	 */
	public function disable_autosave() {
		wp_dequeue_script( 'autosave' );
	}

	/**
	 * On WP post save, look to see whether there's a corresponding EP post,
	 * and if found, sync the EP content into the WP post
	 *
	 * Note that this will overwrite local modifications.
	 * @todo Do more conscientious syncing
	 */
	public function sync_etherpad_content_to_wp( $postdata ) {
		try {
			// We have to concatenaty the getText source
			// differently depending on whether this is a new or
			// existing post
			if ( isset( $_POST['wpep_dummy_post_ID'] ) ) {
				$post_id = (int) $_POST['wpep_dummy_post_ID'];
				$ep_post_id = get_post_meta( $post_id, 'ep_post_group_id', true ) . '$' . get_post_meta( $post_id, 'ep_post_id', true );
			} else {
				$ep_post_id = $this->ep_post_id_concat;
			}

			$text = wpep_client()->getText( $ep_post_id );
			$postdata['post_content'] = $text->text;
		} catch ( Exception $e ) {}

		return $postdata;
	}

	function catch_dummy_post( $post_ID, $post ) {
		if ( isset( $_POST['wpep_dummy_post_ID'] ) ) {
			$dummy_post = get_post( $_POST['wpep_dummy_post_ID'] );
			update_post_meta( $post_ID, 'ep_post_id', get_post_meta( $dummy_post->ID, 'ep_post_id', true ) );
			update_post_meta( $post_ID, 'ep_post_group_id', get_post_meta( $dummy_post->ID, 'ep_post_group_id', true ) );

			$dummy_session_key = 'ep_group_session_id-post_' . $dummy_post->ID;
			$post_session_key = 'ep_group_session_id-post_' . $post_ID;
			update_user_meta( $this->wp_user_id, $post_session_key, get_user_meta( $this->wp_user_id, $dummy_session_key, true ) );
		}
	}
}

?>
