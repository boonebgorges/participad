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
	 * @var string The unique string ID for this module
	 *
	 * @since 1.0
	 */
	public $id;

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
	 * @var string URL of the EP iframe
	 *
	 * @since 1.0
	 */
	public $ep_iframe_url;

	/**
	 * @var string Module base path
	 *
	 * @since 1.0
	 */
	public $module_path;

	/**
	 * @var string Module base path
	 *
	 * @since 1.0
	 */
	public $module_url;

	/**
	 * Steps through the process of setting up basic integration data
	 *
	 * Be sure to call this method early in your module class's constructor
	 *
	 * @since 1.0
	 */
	public function init() {
		$this->set_module_path();
		$this->set_module_url();

		if ( ! participad_is_installed_correctly() ) {
			return new WP_Error( 'not_installed_correctly', 'Participad is not installed correctly.' );
		}

		// Set up the admin panels and save methods
		add_action( 'participad_admin_page', array( $this, 'admin_page' ) );
		add_action( 'participad_admin_page_save', array( $this, 'admin_page_save' ) );
	}

	public function start() {
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
		$this->set_ep_iframe_url();

		if ( isset( $this->ep_post_id ) ) {
			$this->post_ep_setup();
		}
	}

	public function set_module_path() {
		$this->module_path = trailingslashit( PARTICIPAD_PLUGIN_DIR . 'modules/' . $this->id );
	}

	public function set_module_url() {
		$this->module_url = trailingslashit( PARTICIPAD_PLUGIN_URL . 'modules/' . $this->id );
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
	 * This is the method run after the EP post id is successfully set up
	 */
	abstract public function post_ep_setup();

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
	 * Create a session that gives the current user access to this EP post
	 */
	public function create_session() {
		if ( is_a( $this->loggedin_user, 'Participad_User' ) ) {
			$this->loggedin_user->create_session( $this->wp_post_id, $this->ep_post_group_id );
		}
	}

	/**
	 * Look up the EP post id for the current WP post
	 */
	public function set_ep_post_id() {
		if ( ! empty( $this->current_post ) ) {
			$this->ep_post_id = $this->current_post->ep_post_id;
		}
	}

	/**
	 * Calculate the URL for the EP iframe
	 */
	public function set_ep_iframe_url() {
		if ( $this->ep_post_group_id && $this->ep_post_id ) {
			$this->ep_iframe_url = add_query_arg( array(
				'showControls' => 'true',
				'showChat'     => 'false',
				'showLineNumbers' => 'false',
				'useMonospaceFont' => 'false',
			), participad_api_endpoint() . '/p/' . $this->ep_post_group_id . '%24' . $this->ep_post_id );
		}
	}

	/**
	 * Replaces the content of the post with the EP iframe, plus other goodies
	 *
	 * To use this in your own Participad module, filter 'the_content'. Eg:
	 *
	 *   add_filter( 'the_content', array( &$this, 'filter_content' ) );
	 */
	public function filter_content( $content ) {
		$content  = '<iframe id="participad-ep-iframe" src="' . $this->ep_iframe_url . '"></iframe>';
		$content .= '<input type="hidden" id="participad-frontend-post-id" value="' . esc_attr( $this->wp_post_id ) . '" />';
		$content .= wp_nonce_field( 'participad_frontend_nonce', 'participad-frontend-nonce', true, false );
		return $content;
	}

	/**
	 * Catches and process AJAX save requests
	 *
	 * @since 1.0
	 */
	public function save_ajax_callback() {
		check_admin_referer( 'participad_frontend_nonce' );

		$p_post = new Participad_Post( 'wp_post_id=' . $_POST['post_id'] );
		$p_post->sync_wp_ep_content();

		die();
	}

	/**
	 * Markup for the admin page
	 *
	 * Create the markup that'll appears on your module's section of the admin page
	 *
	 * This method is called automatically at the right time. You just need
	 * to override it in your class.
	 */
	public function admin_page() {}

	/**
	 * Save changes on your admin page
	 *
	 * This method is hooked to participad_admin_page_save. Just catch the
	 * $_POST global and do what you need to do
	 */
	public function admin_page_save() {}
}

?>
