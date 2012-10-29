<?php

/**
 * Admin functions
 */

/**
 * Adds the Participad settings menu
 */
function participad_admin_menu() {
	add_options_page(
		__( 'Participad', 'participad' ),
		__( 'Participad', 'participad' ),
		'manage_options',
		'participad',
		'participad_admin_page'
	);
}
add_action( 'admin_menu', 'participad_admin_menu' );

/**
 * Renders the admin panel
 */
function participad_admin_page() {
	$endpoint_is_constant = defined( 'PARTICIPAD_API_ENDPOINT' );
	$key_is_constant      = defined( 'PARTICIPAD_API_KEY' );

	?>

	<form action="<?php echo participad_admin_url() ?>" method="post">

	<div class="wrap">
		<h2><?php _e( 'Participad Settings', 'participad' ) ?></h2>

		<h3><?php _e( 'Etherpad Lite API Details', 'participad' ) ?></h3>

		<p class="description"><?php _e( '<strong>Participad</strong> is a bridge between your WordPress installation and an Etherpad Lite installation. Please enter the API authentication details for your Etherpad Lite installation below.', 'participad' ) ?></p>

		<p class="description"><?php _e( '<a href="http://github.com/Pita/etherpad-lite">Learn more about Etherpad Lite</a>', 'participad' ) ?></p>

		<table class="form-table">
			<tr>
				<th span="row">
					<label for="participad_api_endpoint"><?php _e( 'Etherpad Lite URL', 'participad' ) ?></label>
				</th>

				<td>
					<input <?php if ( $endpoint_is_constant ) : ?>disabled="disabled"<?php endif ?> name="participad_api_endpoint" value="<?php echo esc_attr( participad_api_endpoint() ) ?>" />

					<?php if ( $endpoint_is_constant ) : ?>
						<p class="description"><?php _e( "<code>PARTICIPAD_API_ENDPOINT</code> is defined in <code>wp-config.php</code>.", 'participad' ) ?></p>
					<?php endif ?>
				</td>
			</tr>

			<tr>
				<th span="row">
					<label for="participad_api_key"><?php _e( 'Etherpad Lite API Key', 'participad' ) ?></label>
				</th>

				<td>
					<input <?php if ( $key_is_constant ) : ?>disabled="disabled"<?php endif ?> name="participad_api_key" value="<?php echo esc_attr( participad_api_key() ) ?>" />

					<p class="description"><?php _e( "Found in APIKEY.txt in the root of your Etherpad Lite installation", 'participad' ) ?></p>
					<?php if ( $key_is_constant ) : ?>
						<p class="description"><?php _e( "<code>PARTICIPAD_API_KEY</code> is defined in <code>wp-config.php</code>.", 'participad' ) ?></p>
					<?php endif ?>
				</td>
			</tr>
		</table>

		<?php if ( participad_is_installed_correctly() ) : ?>
			<h3><?php _e( 'Modules', 'participad' ) ?></h3>

			<p class="description"><?php _e( '<strong>Participad Modules</strong> are different ways of enabling your users to collaborate on WordPress content using Etherpad Lite.', 'participad' ) ?></p>

		<?php endif ?>

		<?php do_action( 'participad_admin_page' ) ?>

		<?php wp_nonce_field( 'participad_settings' ) ?>
		<br />
		<input type="submit" name="submit" class="button-primary" value="<?php _e( "Save Changes", 'participad' ) ?>" />
	</div>

	</form>
	<?php
}

/**
 * Catches save requests from our admin page
 */
function participad_admin_page_save() {
	global $pagenow;

	if ( 'options-general.php' != $pagenow ) {
		return;
	}

	if ( empty( $_GET['page'] ) || 'participad' != $_GET['page'] ) {
		return;
	}

	if ( empty( $_POST['submit'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'participad_settings' );

	$endpoint = isset( $_POST['participad_api_endpoint'] ) ? $_POST['participad_api_endpoint'] : '';
	$key      = isset( $_POST['participad_api_key'] ) ? $_POST['participad_api_key'] : '';

	update_option( 'ep_api_endpoint', $endpoint );
	update_option( 'ep_api_key', $key );

	do_action( 'participad_admin_page_save' );
}
add_action( 'admin_init', 'participad_admin_page_save' );

/**
 * Check to see whether Participad is set up correctly, and show a notice
 *
 * @since 1.0
 */
function participad_setup_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( ! participad_is_installed_correctly() ) {
		$html  = '<div class="message error">';
		$html .=   '<p>';

		if ( participad_is_admin_page() ) {
			if ( ! participad_api_endpoint() ) {
				$message = __( 'You must provide a valid Etherpad Lite URL.', 'participad' );
			} else if ( ! participad_api_key() ) {
				$message = __( 'You must provide a valid Etherpad Lite API key. You can find this key in the <code>APIKEY.txt</code> file in the root of your Etherpad Lite installation.', 'participad' );
			} else {
				$message = __( 'We couldn\'t find an Etherpad Lite installation at the URL you provided. Please check the details and try again.', 'participad' );
			}

			$html .= $message;
		} else {
			$html .= sprintf( __( '<strong>Participad is not set up correctly.</strong> Visit the <a href="%s">settings page</a> to learn more.', 'participad' ), participad_admin_url() );
		}

		$html .=   '</p>';
		$html .= '</div>';

		echo $html;
	}
}
add_action( 'admin_notices', 'participad_setup_admin_notice', 999 );

/**
 * Returns the URL of the admin page
 *
 * We need this all over the place, so I've thrown it in a function
 *
 * @return string
 */
function participad_admin_url() {
	return add_query_arg( 'page', 'participad', admin_url( 'options-general.php' ) );
}

/**
 * Is this the Participad admin page?
 *
 * @since 1.0
 * @return bool
 */
function participad_is_admin_page() {
	global $pagenow;

	return 'options-general.php' == $pagenow && isset( $_GET['page'] ) && 'participad' == $_GET['page'];
}

function participad_flush_rewrite_rules() {
        if ( ! is_admin() ) {
                return;
        }

        if ( ! is_super_admin() ) {
                return;
        }

	if ( ! participad_is_installed_correctly() ) {
		return;
	}

        global $wp_rewrite;

        // Check to see whether our rules have been registered yet, by
	// finding a Notepad rule and then comparing it to the registered rules
	foreach ( $wp_rewrite->extra_rules_top as $rewrite => $rule ) {
		if ( 0 === strpos( $rewrite, 'notepads' ) ) {
			$test_rule = $rule;
		}
	}
        $registered_rules = get_option( 'rewrite_rules' );

        if ( ! in_array( $test_rule, $registered_rules ) ) {
                flush_rewrite_rules();
        }
}
add_action( 'admin_init', 'participad_flush_rewrite_rules' );
