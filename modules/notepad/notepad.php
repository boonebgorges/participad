<?php

/**
 * Notepad integration class
 *
 * This is the class that enables the Notepad post type
 *
 * @since 1.0
 */

class Participad_Integration_Notepad extends Participad_Integration {
	var $post_type_name;

	function __construct() {
		$this->id = 'notepad';

		add_action( 'wp_ajax_participad_notepad_autosave', array( $this, 'autosave_ajax_callback' ) ); // @todo nopriv?

		// Calling directly, because we're already past init
		$this->set_post_type_name();
		$this->register_post_type();

		if ( is_wp_error( $this->init() ) ) {
			return;
		}

		add_action( 'wp', array( $this, 'start' ), 1 );
	}

	function set_post_type_name() {
		$this->post_type_name = apply_filters( 'participad_notepad_post_type_name', 'participad_notepad' );
	}

	/**
	 * Registers the Notepad post type
	 *
	 * @since 1.0
	 */
	function register_post_type() {

		$post_type_labels = apply_filters( 'participad_notepad_post_type_labels', array(
			'name' 			=> _x( 'Notepads', 'post type general name', 'participad' ),
			'singular_name' 	=> _x( 'Notepad', 'post type singular name', 'participad' ),
			'add_new' 		=> _x( 'Add New', 'add new', 'participad' ),
			'add_new_item' 		=> __( 'Add New Notepad', 'participad' ),
			'edit_item' 		=> __( 'Edit Notepad', 'participad' ),
			'new_item' 		=> __( 'New Notepad', 'participad' ),
			'view_item' 		=> __( 'View Notepad', 'participad' ),
			'search_items' 		=> __( 'Search Notepads', 'participad' ),
			'not_found' 		=>  __( 'No Notepads found', 'participad' ),
			'not_found_in_trash' 	=> __( 'No Notepads found in Trash', 'participad' ),
			'parent_item_colon' 	=> ''
		), $this );

		// Register the invitation post type
		register_post_type( $this->post_type_name, apply_filters( 'participad_notepad_post_type_args', array(
			'label' 	=> __( 'Notepads', 'participad' ),
			'labels' 	=> $post_type_labels,
			'public' 	=> true,
			'show_ui' 	=> true, // @todo ?
			'hierarchical' 	=> false,
			'supports' 	=> array( 'title', 'editor', 'custom-fields' ),
			'rewrite'       => array(
				'with_front' => false,
				'slug'       => 'notepads'
			),
		), $this ) );

	}

	/**
	 * Will an Etherpad instance appear on this page?
	 *
	 * @return bool
	 */
	public function load_on_page() {
		$queried_object = get_queried_object();
		return isset( $queried_object->post_type ) && $this->post_type_name == $queried_object->post_type;
	}

	/**
	 * The WP post ID is easy to set in this case
	 */
	public function set_wp_post_id() {
		$this->wp_post_id = get_the_ID();
	}

	/**
	 * The setup functions that happen after the EP id has been determined:
	 *   - Enqueue styles/scripts
	 *   - Filter the_content to put the EP instance on the page
	 */
	public function post_ep_setup() {
		if ( is_user_logged_in() && ! empty( $this->loggedin_user->ep_session_id ) ) {
			$this->enqueue_styles();
			$this->enqueue_scripts();
			add_action( 'the_content', array( $this, 'filter_content' ) );
		}
	}

	/**
	 * Replaces the content of the post with the EP iframe, plus other goodies
	 */
	public function filter_content( $content ) {
		$content  = '<iframe id="participad-notepad" src="' . $this->ep_iframe_url . '"></iframe>';
		$content .= '<input type="hidden" id="notepad-post-id" value="' . esc_attr( $this->wp_post_id ) . '" />';
		$content .= wp_nonce_field( 'participad_notepad_autosave', 'participad-notepad-nonce', true, false );
		return $content;
	}

	public function autosave_ajax_callback() {
		check_admin_referer( 'participad_notepad_autosave' );

		$p_post = new Participad_Post( 'wp_post_id=' . $_POST['post_id'] );
		$p_post->sync_wp_ep_content();

		die();
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
			if ( isset( $_POST['participad_dummy_post_ID'] ) ) {
				$post_id = (int) $_POST['participad_dummy_post_ID'];
				$ep_post_id = get_post_meta( $post_id, 'ep_post_group_id', true ) . '$' . get_post_meta( $post_id, 'ep_post_id', true );
			} else {
				$ep_post_id = $this->ep_post_id_concat;
			}

			$text = participad_client()->getText( $ep_post_id );
			$postdata['post_content'] = $text->text;
		} catch ( Exception $e ) {}

		return $postdata;
	}

	/**
	 * When creating a new post, we need to copy over the metadata from
	 * the dummy WP post into the actual WP post
	 */
	function catch_dummy_post( $post_ID, $post ) {
		if ( isset( $_POST['participad_dummy_post_ID'] ) ) {
			$dummy_post = get_post( $_POST['participad_dummy_post_ID'] );
			update_post_meta( $post_ID, 'ep_post_id', get_post_meta( $dummy_post->ID, 'ep_post_id', true ) );
			update_post_meta( $post_ID, 'ep_post_group_id', get_post_meta( $dummy_post->ID, 'ep_post_group_id', true ) );

			$dummy_session_key = 'ep_group_session_id-post_' . $dummy_post->ID;
			$post_session_key = 'ep_group_session_id-post_' . $post_ID;
			update_user_meta( $this->wp_user_id, $post_session_key, get_user_meta( $this->wp_user_id, $dummy_session_key, true ) );
		}
	}

	public function enqueue_styles() {
		wp_enqueue_style( 'participad_editor', $this->module_url . 'css/notepad.css' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'schedule' );
		wp_enqueue_script( 'participad_notepad', $this->module_url . 'js/notepad.js', array( 'jquery', 'schedule' ) );
		wp_localize_script( 'participad_notepad', 'Participad_Notepad', array(
			'autosave_interval' => AUTOSAVE_INTERVAL
		) );
	}

	//////////////////
	//  SETTINGS    //
	//////////////////

	public function settings_panel() {

	}

}
