<?php

/*
 * Will use the Etherpad lite client API found here: https://github.com/TomNomNom/etherpad-lite-client
 * version: not specified heh
 */
require WP_ETHERPAD_PLUGIN_DIR . 'lib/etherpad-lite-client.php';

class WP_Etherpad {

	/**
	 * @var Instance of the Etherpad Lite client
	 */
	private $ep;

	/**
	 * @var int ID of the current WP post (empty in the case of new posts)
	 *
	 * @since 1.0
	 */
	private $wp_post_id;

	/**
	 * @var int ID of the current EP post (empty in the case of new posts)
	 *
	 * @since 1.0
	 */
	private $ep_post_id;

	/**
	 * Use me to bootstrap
	 *
	 * @since 1.0
	 */
	function &init() {
		static $instance = false;

		if ( empty( $instance ) ) {
			$instance = new WP_Etherpad;
		}

		return $instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct(){
		if ( !$this->load_on_page() ) {
			return;
		}

		$this->ep = new EtherpadLiteClient(
			WP_ETHERPAD_API_KEY,
			WP_ETHERPAD_API_ENDPOINT
		);

		$this->set_wp_post_id();
		$this->set_ep_post_id();

		add_action('the_editor', array( &$this, 'editor' ));
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

	function editor( $editor ) {
		echo '<style type="text/css">#wp-content-editor-container iframe { width: 100%; height: 400px; }</style>';
		$editor = preg_replace( '|<textarea.+?/textarea>|', "<iframe src='" . WP_ETHERPAD_API_ENDPOINT . "/p/" . $this->ep_post_id . "?showControls=true&amp;showChat=false&amp;showLineNumbers=true&amp;useMonospaceFont=false' height=400></iframe>", $editor );
		return $editor;
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
				$foo = $this->ep->createPad( $ep_post_id );
				$pad_created = true;
			} catch ( Exception $e ) {
				$ep_post_id = self::generate_random();
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


}

?>
