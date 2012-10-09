<?php

/**
 * Widgets for the Notepad module
 */

/**
 * Fire up our widgets
 */
function participad_notepad_register_widgets() {
	register_widget( 'Participad_Notepad_Create_Widget' );
}
add_action( 'widgets_init', 'participad_notepad_register_widgets' );

/**
 * The Create Notepad widget
 */
class Participad_Notepad_Create_Widget extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'participad_notepad_create',
			__( 'Create A Notepad', 'participad' ),
			array(
				'description' => __( 'An easy interface for creating new Participad Notepads.', 'participad' )
			)
		);
	}

	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Create A Notepad', 'participad' );
		$suppress_styles = (bool) $instance['suppress_styles'];

		?>

		<p>
			<label for="<?php echo $this->get_field_name( 'title' ) ?>"><?php _e( 'Title:', 'participad' ) ?></label>
			<input name="<?php echo $this->get_field_name( 'title' ) ?>" id="<?php echo $this->get_field_name( 'title' ) ?>" value="<?php echo esc_attr( $title ) ?>" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_name( 'suppress_styles' ) ?>"><?php _e( 'Suppress styles?', 'participad' ) ?></label>
			<input type="checkbox" name="<?php echo $this->get_field_name( 'suppress_styles' ) ?>" id="<?php echo $this->get_field_name( 'suppress_styles' ) ?>" value="1" <?php checked( $suppress_styles ) ?>/>
			<br />
			<span class="description"><?php _e( 'By default, Participad loads a few widget styles. Check this box if you\'d rather provide your own CSS.', 'participad' ) ?></span>

		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['suppress_styles'] = isset( $new_instance['suppress_styles'] ) ? 1 : 0;
		return $instance;
	}

	/**
	 * The widget
	 */
	public function widget( $args, $instance ) {
		extract( $args );

		$title = $instance['title'];
		$suppress_styles = (bool) $instance['suppress_styles'];

		if ( ! $suppress_styles ) {
			echo '<style type="text/css">
				.widget_participad_notepad_create { font-size: .8em; }
				.widget_participad_notepad_create #notepad-name { width: 90%; }
				.widget_participad_notepad_create td { width: 50%; padding-bottom: 10px; }
			</style>';
		}

		echo $before_widget;

		if ( ! empty( $title ) ) {
			echo $before_title . $title . $after_title;
		}

		echo participad_notepad_create_render();

		echo $after_widget;
	}
}

