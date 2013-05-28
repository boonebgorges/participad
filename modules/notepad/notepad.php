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

	/**
	 * @since 1.0
	 */
	function __construct() {
		$this->id = 'notepad';

		if ( is_wp_error( $this->init() ) ) {
			return;
		}

		if ( 'no' === participad_is_module_enabled( 'notepad' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'set_post_type_name' ), 20 );
		add_action( 'init', array( $this, 'register_post_type' ), 30 );

		// Required files
		require( $this->module_path . 'widgets.php' );

		// BuddyPress integration should load at bp_init
		add_action( 'bp_init', array( $this, 'bp_integration' ) );

		// Load at 'wp', at which point the $wp_query global has been populated
		add_action( 'wp', array( $this, 'start' ), 1 );
	}

	/**
	 * Post type name is abstracted out so it can be overridden as necessary
	 *
	 * @since 1.0
	 */
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
			'show_ui' 	=> current_user_can( 'manage_options' ),
			'hierarchical' 	=> false,
			'supports' 	=> array( 'title', 'editor', 'custom-fields' ),
			'has_archive'   => true,
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
	 *
	 * @since 1.0
	 */
	public function set_wp_post_id() {
		$this->wp_post_id = get_the_ID();
	}

	/**
	 * The setup functions that happen after the EP id has been determined:
	 *   - Enqueue styles/scripts
	 *   - Remove the Edit link
	 *   - Filter the_content to put the EP instance on the page
	 *
	 * @since 1.0
	 */
	public function post_ep_setup() {
		if ( is_user_logged_in() && ! empty( $this->loggedin_user->ep_session_id ) ) {
			$this->enqueue_styles();
			$this->enqueue_scripts();
			add_filter( 'edit_post_link', array( &$this, 'edit_post_link' ), 10, 2 );
			add_action( 'the_content', array( $this, 'filter_content' ) );
		}
	}

	/**
	 * Load the BuddyPress integration piece
	 *
	 * @since 1.0
	 */
	public function bp_integration() {
		require( $this->module_path . 'bp-integration.php' );
	}

	/**
	 * We don't need an Edit link on Notepads
	 *
	 * @since 1.0
	 */
	public function edit_post_link( $link, $post_id ) {
		return '';
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
		wp_enqueue_style( 'participad_notepad', $this->module_url . 'css/notepad.css' );
	}

	public function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'schedule' );
		wp_enqueue_script( 'participad_frontend', PARTICIPAD_PLUGIN_URL . 'modules/frontend/js/frontend.js', array( 'jquery' ) );
		wp_enqueue_script( 'participad_notepad', $this->module_url . 'js/notepad.js', array( 'jquery', 'participad_frontend', 'schedule' ) );
		wp_localize_script( 'participad_notepad', 'Participad_Notepad', array(
			'autosave_interval' => participad_notepad_autosave_interval(),
		) );
	}

	//////////////////
	//  SETTINGS    //
	//////////////////

	public function admin_page() {
		$enabled = participad_is_module_enabled( 'notepad' );

		?>

		<h4><?php _e( 'Notepad', 'participad' ) ?></h4>

		<p class="description"><?php _e( 'The Notepad module gives your users a handy way to take collaborative notes. Notepads are front-end note-taking spaces, stored in a WordPress custom post type, and optionally associated with non-Notepad posts. If you\'re using Notepads, you may want to enable some of our helpful widgets and shortcodes.', 'participad' ) ?></p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="participad-notepad-enable"><?php _e( 'Enable Participad Notepads', 'participad' ) ?></label>
				</th>

				<td>
					<select id="participad-notepad-enable" name="participad-notepad-enable">
						<option value="yes" <?php selected( $enabled, 'yes' ) ?>><?php _e( 'Yes', 'participad' ) ?></option>
						<option value="no" <?php selected( $enabled, 'no' ) ?>><?php _e( 'No', 'participad' ) ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	public function admin_page_save() {
		$enabled = isset( $_POST['participad-notepad-enable'] ) && 'no' == $_POST['participad-notepad-enable'] ? 'no' : 'yes';
		update_option( 'participad_notepad_enable', $enabled );
	}
}

/* Here is a line about mustard */

/**
 * Get the 'participad_notepad' post type name
 *
 * @since 1.0
 * @return str
 */
function participad_notepad_post_type_name() {
	$p = Participad::instance();
	return $p->modules['notepad']->post_type_name;
}

/**
 * Is this a Notepad object?
 *
 * @since 1.0
 * @return bool
 */
function participad_notepad_is_notepad() {
	$queried_object = get_queried_object();
	return isset( $queried_object->post_type ) && participad_notepad_post_type_name() == $queried_object->post_type;
}

/**
 * Get the autosave interval
 *
 * Falls back on WP's main AUTOSAVE_INTERVAL
 *
 * Set your own by defining PARTICIPAD_NOTEPAD_AUTOSAVE_INTERVAL, or by
 * filtering participad_notepad_autosave_interval. Note that the filter
 * always takes precedence.
 *
 * @since 1.0
 * @return int
 */
function participad_notepad_autosave_interval() {
	if ( defined( 'PARTICIPAD_NOTEPAD_AUTOSAVE_INTERVAL' ) ) {
		$interval = (int) PARTICIPAD_NOTEPAD_AUTOSAVE_INTERVAL;
	}

	if ( empty( $interval ) ) {
		$interval = AUTOSAVE_INTERVAL;
	}

	return apply_filters( 'participad_notepad_autosave_interval', $interval );
}

/**
 * If this post has an associated Notepad, return its ids
 *
 * @since 1.0
 * @return array
 */
function participad_notepad_post_has_notepad( $post_id = 0 ) {
	$notepads = array();

	if ( ! $post_id && is_single() ) {
		$post_id = get_the_ID();
	}

	if ( ! $post_id ) {
		return $notepads;
	}

	$posts = get_posts( array(
		'post_type'  => participad_notepad_post_type_name(),
		'meta_query' => array(
			array(
				'key'   => 'notepad_associated_post',
				'value' => $post_id,
			),
		),
		'post_status' => 'publish',
		'posts_per_page' => -1,
	) );

	return $posts;
}

/**
 * Builds the HTML for the Create A Notepad widget and shortcode
 *
 * @param array $args See below for values
 * @return string $form The HTML form
 */
function participad_notepad_create_render( $args = array() ) {
	$r = wp_parse_args( $args, array(
		'default_title'           => '',
		'default_associated_post' => '',
		'use_packaged_css'        => true,
	) );

	// Pull up a list of posts to populate the Link To field
	$associated_post_args = array(
		'post_type'              => array( 'post', 'page' ),
		'post_status'            => 'publish',
		'posts_per_page'         => -1,
		'update_post_term_cache' => false,
		'update_post_meta_cache' => false,
		'orderby'                => 'title',
		'order'                  => 'ASC',
	);
	$associated_posts = get_posts( $associated_post_args );
	$associated_posts_options = '';
	foreach( $associated_posts as $ap ) {
		$selected = $ap->ID == $r['default_associated_post'] ? ' selected="selected" ' : '';
		$associated_posts_options .= '<option value="' . esc_attr( $ap->ID ) . '"' . $selected . '>' . esc_attr( $ap->post_title ) . '</option>';
	}

	$form = '';

	if ( (bool) $r['use_packaged_css'] ) {
		$form .= '<style type="text/css">
			form.notepad-create { font-size: .8em; }
			form.notepad-create ul { list-style-type: none; }
			form.notepad-create li { margin-bottom: .5em; }
			form.notepad-create label { display: block; float: left; width: 120px; }
			form.notepad-create input[type="text"] { width: 50%; }
			form.notepad-create select { width: 50%; }
			form.notepad-create input[type="submit"] { margin-top: .5em; }
		</style>';
	}

	// @todo
	if ( ! is_user_logged_in() ) {
		return sprintf( __( 'You <a href="%s">log in</a> to create Notepads.', 'participad' ), add_query_arg( 'redirect_to', wp_guess_url(), wp_login_url() ) );
	}

	$form .= '<form class="notepad-create" method="post" action="">';
	$form .=   '<ul class="participad-form-list">';

	$form .=     '<li>';
	$form .=       '<label for="notepad-name">' . __( 'Notepad Title:', 'participad' ) . '</label>';
	$form .=       '<input type="text" name="notepad-name" id="notepad-name" value="' . esc_attr( $r['default_title'] ) . '" />';
	$form .=     '</li>';

	$form .=     '<li>';
	$form .=       '<label for="notepad-associated-post">' . __( 'Link Notepad To:', 'participad' ) . '</label>';
	$form .=       '<select name="notepad-associated-post" id="notepad-associated-post">';
	$form .=         '<option>' . __( '- None -', 'participad' ) . '</option>';
	$form .=         $associated_posts_options;
	$form .=       '</select>';
	$form .=     '</li>';

	$form .=   '</ul>';
	$form .=   '<input type="submit" name="participad-create-submit" id="participad-create-submit" value="' . __( 'Create Notepad', 'participad' ) . '" />';
	$form .=   wp_nonce_field( 'participad_notepad_create', 'participad-notepad-nonce', true, false );
	$form .= '</form>';

	return $form;
}

/**
 * Registers our notepad_create shortcode
 */
function participad_notepad_create_shortcode( $atts ) {
	return participad_notepad_create_render( $atts );
}
add_shortcode( 'notepad_create', 'participad_notepad_create_shortcode' );

/**
 * Catches and processes notepad creation requests
 */
function participad_notepad_create_catch() {
	if ( is_user_logged_in() && ! empty( $_POST['participad-create-submit'] ) ) {

		check_admin_referer( 'participad_notepad_create', 'participad-notepad-nonce' );

		$errors  = array();

		if ( empty( $_POST['notepad-name'] ) ) {
			$errors['notepad_noname'] = '1';
		}

		$associated_post = isset( $_POST['notepad-associated-post'] ) ? (int) $_POST['notepad-associated-post'] : 0;

		$notepad_id = participad_notepad_create_notepad( array(
			'name'            => $_POST['notepad-name'],
			'associated_post' => $associated_post,
			'author'          => get_current_user_id(),
		) );

		if ( $notepad_id ) {
			$redirect = add_query_arg( 'notepad_created', '1', get_permalink( $notepad_id ) );
		} else {
			$errors['notepad_misc'] = '1';
			$redirect = add_query_arg( $errors, $_POST['_wp_http_referer'] );
		}

		wp_safe_redirect( $redirect );
	}
}
add_action( 'wp', 'participad_notepad_create_catch', 1 );

/**
 * Create a new notepad
 */
function participad_notepad_create_notepad( $args = array() ) {
	$r = wp_parse_args( $args, array(
		'name'            => '',
		'associated_post' => '',
		'author'          => '',
	) );

	$notepad_id = wp_insert_post( array(
		'post_author' => $r['author'],
		'post_title'  => $r['name'],
		'post_status' => 'publish',
		'post_type'   => participad_notepad_post_type_name(),
	) );

	if ( $notepad_id ) {
		update_post_meta( $notepad_id, 'notepad_associated_post', $r['associated_post'] );
	}

	return $notepad_id;
}

/**
 * Detect when a success/error message should be shown
 */
function participad_notepad_display_error( $content ) {
	$message = '';

	// Admins can override these hardcoded styles
	if ( ! apply_filters( 'participad_notepad_suppress_error_styles', false ) ) {
		$message .= '
			<style type="text/css">
				div.participad-message { padding: 10px 15px; border: 1px solid #ccc; border-radius: 2px; margin-bottom: 1em; }
				div.participad-message-success { background: #ffffe0; }
				div.participad-message-error { background: #c43; }
			</style>
		';
	}

	if ( participad_notepad_is_notepad() && isset( $_GET['notepad_created'] ) ) {
		$message .= '<div class="participad-message participad-message-success">' . __( 'Notepad created', 'participad' ) . '</div>';
	}

	if ( isset( $_GET['notepad_noname'] ) ) {
		$message .= '<div class="participad-message participad-message-error">' . __( 'Notepads must have a title', 'participad' ) . '</div>';
	}

	if ( isset( $_GET['notepad_misc'] ) ) {
		$message .= '<div class="participad-message participad-message-error">' . __( 'Could not create the notepad', 'participad' ) . '</div>';
	}

	return $message . $content;
}
add_filter( 'the_content', 'participad_notepad_display_error', 100 );
