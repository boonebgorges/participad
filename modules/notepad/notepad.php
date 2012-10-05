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

	public function set_wp_post_id() {
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
				'post_title'   => 'Participad_Dummy_Post',
				'post_content' => '',
				'post_status'  => 'auto-draft'
			) );

			$this->localize_script['dummy_post_ID'] = $wp_post_id;
		}

		$this->wp_post_id = (int) $wp_post_id;

	}

	public function post_ep_setup() {
		if ( is_user_logged_in() && ! empty( $this->loggedin_user->ep_session_id ) ) {
			add_action( 'wp_footer', array( $this, 'load_styles' ) );
			add_action( 'the_content', array( $this, 'filter_content' ) );
		}
	}

	public function filter_content( $content ) {
		return '<iframe id="participad-notepad" src="' . $this->ep_iframe_url . '"></iframe>';
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

	/**
	 * We have to load the styles directly in the footer, because of
	 * load order issues with wp_enqueue_style()
	 */
	public function load_styles() {
		echo "<link rel='stylesheet' href='" . $this->module_url . "css/notepad.css' type='text/css' media='all' />";
	}

	public function enqueue_scripts() {

	}

	//////////////////
	//  SETTINGS    //
	//////////////////

	public function settings_panel() {

	}

}
