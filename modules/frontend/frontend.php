<?php

/**
 * Frontend integration class
 *
 * This is the class that enables frontend editing of normal posts
 *
 * Note that the main methods required for implementing Participad on the front
 * end of WP are already implemented in the Participad_Integration base class.
 * This class only extends the functionality to the Edit button at the bottom
 * of posts on the front-end.
 *
 * @since 1.0
 */

class Participad_Integration_Frontend extends Participad_Integration {
	function __construct() {
		$this->id = 'frontend';

		if ( is_wp_error( $this->init() ) ) {
			return;
		}

		if ( 'no' == get_option( 'participad_frontend_enable' ) ) {
			return;
		}

		add_action( 'wp_ajax_participad_frontend_save', array( $this, 'save_ajax_callback' ) ); // @todo nopriv?

		// Load at 'wp', at which point the $wp_query global has been populated
		add_action( 'wp', array( $this, 'start' ), 1 );
	}

	/**
	 * Will an Etherpad instance appear on this page?
	 *
	 * @todo How to make this more fine-grained through user settings?
	 * @return bool
	 */
	public function load_on_page() {
		return apply_filters( 'participad_frontend_load_on_page', true );
	}

	/**
	 * The setup functions that happen after the EP id has been determined:
	 *   - Filter the Edit link to point to the correct frontend URL
	 *   - Set up the filter on the_content, where necessary
	 *   - Enqueue styles/scripts
	 *
	 * @since 1.0
	 */
	public function post_ep_setup() {
		if ( is_user_logged_in() && ! empty( $this->loggedin_user->ep_session_id ) ) {
			add_filter( 'edit_post_link', array( &$this, 'edit_post_link' ), 10, 2 );
			$this->maybe_filter_content();
			$this->enqueue_scripts();
			$this->enqueue_styles();
		}
	}

	/**
	 * The WP post ID is easy to set in this case
	 */
	public function set_wp_post_id() {
		$this->wp_post_id = get_the_ID();
	}

	/**
	 * Filter the Edit button on the front end of the post
	 *
	 * @since 1.0
	 * @param string $link The original edit link
	 * @param int $post_id The id of the post in question
	 * @return string $link The new HTML link element
	 */
	public function edit_post_link( $link, $post_id ) {
		if ( empty( $_GET['participad_edit'] ) ) {
			$new_link = add_query_arg( 'participad_edit', '1', get_permalink( $post_id ) );
			$link = preg_replace( '/href=".*?"/', 'href="' . $new_link . '"', $link );
		} else {
			$new_link = remove_query_arg( 'participad_edit', get_permalink( $post_id ) );
			$link = '<a class="participad-exit-edit-mode" href="' . $new_link . '">' . __( 'Exit Edit Mode', 'participad' ) . '</a>';
		}

		return $link;
	}

	/**
	 * Set up the filter_content filter, if necessary
	 *
	 * We don't want to create the Etherpad in every case. We must check
	 * permissions first.
	 *
	 * @since 1.0
	 */
	public function maybe_filter_content() {
		global $post;

		if ( empty( $_GET['participad_edit'] ) ) {
			return;
		}

		$post_type_object = get_post_type_object( $post->post_type );
		if ( empty( $post_type_object->cap->edit_post ) || ! current_user_can( $post_type_object->cap->edit_post, $post->ID ) ) {
			return;
		}

        // This is the main content filter that adds the Etherpad interface
		add_action( 'the_content', array( $this, 'filter_content' ) );

        // Adds some additional text to the content box
		add_action( 'the_content', array( $this, 'filter_content_helptext' ), 20 );
	}

    /**
     * Adds help text underneath Etherpad instances on the front end
     *
     * @since 1.0
     *
     * @param string $content The content, which should already have the
     *   WP content swapped out with the iframe (see maybe_filter_content())
     * @return string $content The content with our text appended
     */
    public function filter_content_helptext( $content ) {
        $content .= '<p class="participad-frontend-helptext">' . sprintf( __( "You're in collaborative edit mode. <a href='%s' class='participad-exit-edit-mode'>Return to standard mode</a>.", 'participad' ), remove_query_arg( 'participad_edit', get_permalink() ) ) . '</p>';
        return $content;
    }

	/**
	 * Enqueue necessary scripts
	 *
	 * @since 1.0
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'participad_frontend', $this->module_url . 'js/frontend.js', array( 'jquery' ) );
        wp_localize_script( 'participad_frontend', 'Participad_Frontend', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
        ) );
	}

	/**
	 * Enqueue necessary styles
	 *
	 * @since 1.0
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'participad_frontend', $this->module_url . 'css/frontend.css' );
	}

	public function admin_page() {
		$enabled = get_option( 'participad_frontend_enable' );
		if ( ! in_array( $enabled, array( 'yes', 'no' ) ) ) {
			$enabled = 'yes';
		}

		?>

		<h4><?php _e( 'Frontend', 'participad' ) ?></h4>

		<p class="description"><?php _e( 'The Frontend module allows you to edit WordPress content, using Etherpad, without visiting the Dashboard. When this module is enabled, the Edit link that permitted users see on the front-end will refresh the page, with an Etherpad Lite instance.', 'participad' ) ?></p>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="participad-frontend-enable"><?php _e( 'Enable Participad on the Front End', 'participad' ) ?></label>
				</th>

				<td>
					<select id="participad-frontend-enable" name="participad-frontend-enable">
						<option value="yes" <?php selected( $enabled, 'yes' ) ?>><?php _e( 'Yes', 'participad' ) ?></option>
						<option value="no" <?php selected( $enabled, 'no' ) ?>><?php _e( 'No', 'participad' ) ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	public function admin_page_save() {
		$enabled = isset( $_POST['participad-frontend-enable'] ) && 'no' == $_POST['participad-frontend-enable'] ? 'no' : 'yes';
		update_option( 'participad_frontend_enable', $enabled );
	}
}
