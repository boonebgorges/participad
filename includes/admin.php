<?php

/**
 * Admin functions
 */

/**
 * Adds the Etherpad settings menu
 */
function wpep_admin_menu() {
	add_options_page(
		'Etherpad',
		'Etherpad Settings',
		'manage_options',
		'etherpad',
		'wpep_admin_page'
	);
}
add_action( 'admin_menu', 'wpep_admin_menu' );

/**
 * Renders the admin panel
 */
function wpep_admin_page() {
	$endpoint_is_constant = defined( 'WPEP_API_ENDPOINT' );
	$key_is_constant      = defined( 'WPEP_API_KEY' );

	?>

	<form action="<?php echo wpep_admin_url() ?>" method="post">

	<div class="wrap">
		<h2><?php _e( 'Etherpad', 'wpep' ) ?></h2>

		<table class="form-table">
			<tr>
				<th span="row">
					<label for="wpep_api_endpoint"><?php _e( 'Etherpad Lite URL', 'wpep' ) ?></label>
				</th>

				<td>
					<input <?php if ( $endpoint_is_constant ) : ?>disabled="disabled"<?php endif ?> name="wpep_api_endpoint" value="<?php echo esc_attr( wpep_api_endpoint() ) ?>" />

					<?php if ( $endpoint_is_constant ) : ?>
						<p class="description"><?php _e( "<code>WPEP_API_ENDPOINT</code> is defined in <code>wp-config.php</code>.", 'wpep' ) ?></p>
					<?php endif ?>
				</td>
			</tr>

			<tr>
				<th span="row">
					<label for="wpep_api_key"><?php _e( 'Etherpad Lite API Key', 'wpep' ) ?></label>
				</th>

				<td>
					<input <?php if ( $key_is_constant ) : ?>disabled="disabled"<?php endif ?> name="wpep_api_key" value="<?php echo esc_attr( wpep_api_key() ) ?>" />

					<p class="description"><?php _e( "Found in APIKEY.txt in the root of your Etherpad Lite installation", 'wpep' ) ?></p>
					<?php if ( $key_is_constant ) : ?>
						<p class="description"><?php _e( "<code>WPEP_API_KEY</code> is defined in <code>wp-config.php</code>.", 'wpep' ) ?></p>
					<?php endif ?>
				</td>
			</tr>
		</table>

		<?php wp_nonce_field( 'wpep_settings' ) ?>
		<input type="submit" name="submit" class="button-primary" value="<?php _e( "Save Changes", 'wpep' ) ?>" />
	</div>

	</form>
	<?php
}

/**
 * Catches save requests from our admin page
 */
function wpep_admin_page_save() {
	global $pagenow;

	if ( 'options-general.php' != $pagenow ) {
		return;
	}

	if ( empty( $_GET['page'] ) || 'etherpad' != $_GET['page'] ) {
		return;
	}

	if ( empty( $_POST['submit'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	check_admin_referer( 'wpep_settings' );

	$endpoint = isset( $_POST['wpep_api_endpoint'] ) ? $_POST['wpep_api_endpoint'] : '';
	$key      = isset( $_POST['wpep_api_key'] ) ? $_POST['wpep_api_key'] : '';

	update_option( 'ep_api_endpoint', $endpoint );
	update_option( 'ep_api_key', $key );
}
add_action( 'admin_init', 'wpep_admin_page_save' );

/**
 * Returns the URL of the admin page
 *
 * We need this all over the place, so I've thrown it in a function
 *
 * @return string
 */
function wpep_admin_url() {
	return add_query_arg( 'page', 'etherpad', admin_url( 'options-general.php' ) );
}

