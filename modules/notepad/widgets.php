<?php

/**
 * Widgets for the Notepad module
 */

/**
 * Fire up our widgets
 */
function participad_notepad_register_widgets() {
	register_widget( 'Participad_Notepad_Create_Widget' );
	register_widget( 'Participad_Notepad_Info_Widget' );
}
add_action( 'widgets_init', 'participad_notepad_register_widgets' );

/**
 * The Create Notepad widget
 */
class Participad_Notepad_Create_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'participad_notepad_create',
			__( '(Participad) Create A Notepad', 'participad' ),
			array(
				'description' => __( 'An easy interface for creating new Participad Notepads.', 'participad' )
			)
		);
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Create A Notepad', 'participad' );
		$use_packaged_css = isset( $instance['use_packaged_css'] ) && 'no' == $instance['use_packaged_css'] ? 'no' : 'yes';

		?>

		<p>
			<label for="<?php echo $this->get_field_name( 'title' ) ?>"><?php _e( 'Title:', 'participad' ) ?></label>
			<input name="<?php echo $this->get_field_name( 'title' ) ?>" id="<?php echo $this->get_field_name( 'title' ) ?>" value="<?php echo esc_attr( $title ) ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_name( 'use_packaged_css' ) ?>"><?php _e( 'Use packaged CSS:', 'participad' ) ?></label>
			<select name="<?php echo $this->get_field_name( 'use_packaged_css' ) ?>" id="<?php echo $this->get_field_name( 'use_packaged_css' ) ?>">
				<option value="yes" <?php selected( $use_packaged_css, 'yes' ) ?>><?php _e( 'Yes', 'participad' ) ?></option>
				<option value="no" <?php selected( $use_packaged_css, 'no' ) ?>><?php _e( 'No', 'participad' ) ?></option>
			</select>

		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['use_packaged_css'] = isset( $new_instance['use_packaged_css'] ) && 'no' == $new_instance['use_packaged_css'] ? 'no' : 'yes';
		return $instance;
	}

	/**
	 * The widget
	 */
	public function widget( $args, $instance ) {
		extract( $args );

		$title = $instance['title'];
		$use_packaged_css = ! isset( $instance['use_packaged_css'] ) || 'no' == $instance['use_packaged_css'] ? 'no' : 'yes';

		echo $before_widget;

		if ( ! empty( $title ) ) {
			echo $before_title . $title . $after_title;
		}

                $args = array();
                if ( is_single() ) {
                        $args['default_associated_post'] = get_the_ID();
                }

		echo participad_notepad_create_render( $args );

		echo $after_widget;
	}
}

/**
 * The Notepad Info widget
 */
class Participad_Notepad_Info_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'participad_notepad_Info',
			__( '(Participad) Notepad Info', 'participad' ),
			array(
				'description' => __( 'Displays Notepad info. When you\'re viewing a Notepad, shows info about associated posts. When you\'re viewing a post, shows info about associated notepads.', 'participad' )
			)
		);
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Notepad Info', 'participad' );
		$use_packaged_css = isset( $instance['use_packaged_css'] ) && 'no' == $instance['use_packaged_css'] ? 'no' : 'yes';

		?>

		<p>
			<label for="<?php echo $this->get_field_name( 'title' ) ?>"><?php _e( 'Title:', 'participad' ) ?></label>
			<input name="<?php echo $this->get_field_name( 'title' ) ?>" id="<?php echo $this->get_field_name( 'title' ) ?>" value="<?php echo esc_attr( $title ) ?>" />
		</p>

		<?php /* Not using at the moment */ /*
		<p>
			<label for="<?php echo $this->get_field_name( 'use_packaged_css' ) ?>"><?php _e( 'Use packaged CSS:', 'participad' ) ?></label>
			<select name="<?php echo $this->get_field_name( 'use_packaged_css' ) ?>" id="<?php echo $this->get_field_name( 'use_packaged_css' ) ?>">
				<option value="yes" <?php selected( $use_packaged_css, 'yes' ) ?>><?php _e( 'Yes', 'participad' ) ?></option>
				<option value="no" <?php selected( $use_packaged_css, 'no' ) ?>><?php _e( 'No', 'participad' ) ?></option>
			</select>
		</p>
		<?php */ ?>

		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['use_packaged_css'] = isset( $new_instance['use_packaged_css'] ) && 'no' == $new_instance['use_packaged_css'] ? 'no' : 'yes';
		return $instance;
	}

	/**
	 * The widget
	 */
	public function widget( $args, $instance ) {
		extract( $args );

		$content = '';

		// If this is neither a Notepad or a post with an associated
		// Notepad, there's nothing to display
		if ( participad_notepad_is_notepad() && $associated_post_id = get_post_meta( get_the_ID(), 'notepad_associated_post', true ) ) {
			$content .= '<p>' . __( 'This Notepad is associated with the following post:', 'participad' ) . '</p>';
			$associated_post = get_post( $associated_post_id );
			$content .= '<ul class="associated-post"><li><a href="' . get_permalink( $associated_post_id ) . '">' . esc_html( $associated_post->post_title ) . '</a></li></ul>';
		} else if ( ! participad_notepad_is_notepad() && $notepads = participad_notepad_post_has_notepad() ) {
			$content .= '<p>' . __( 'This post is associated with the following Notepads:', 'participad' ) . '</p>';
			$content .= '<ul class="associated-notepads">';
			foreach ( $notepads as $np ) {
				$content .= '<li><a href="' . get_permalink( $np->ID ) . '">' . esc_html( $np->post_title ) . '</a></li>';
			}
			$content .= '</ul>';
		}

		if ( $content ) {
			$title = $instance['title'];
			$use_packaged_css = ! isset( $instance['use_packaged_css'] ) || 'no' == $instance['use_packaged_css'] ? 'no' : 'yes';

			if ( 'yes' == $use_packaged_css ) {
				echo '<style type="text/css">
					.widget_participad_notepad_create { font-size: .8em; }
				</style>';
			}

			echo $before_widget;

			if ( ! empty( $title ) ) {
				echo $before_title . $title . $after_title;
			}

			echo $content;
			echo $after_widget;
		}
	}
}

