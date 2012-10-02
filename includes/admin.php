<?php

/**
 * Admin functions
 */

/**
 * Adds the Participad settings menu
 */
function participad_admin_menu() {
	add_options_page(
		'Participad',
		'Participad Settings',
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

		<?php wp_nonce_field( 'participad_settings' ) ?>
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
}
add_action( 'admin_init', 'participad_admin_page_save' );

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

