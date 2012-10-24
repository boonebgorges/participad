<?php

/**
 * Utility function for referencing the API client singleton
 *
 * For example:
 *
 *     $text = participad_client()->getText( $ep_post_id );
 *
 * @since 1.0
 */
function participad_client() {
	return participad_Client::instance();
}

/**
 * Returns the API key, as set either in the DB or in a constant
 *
 * At the moment, the plugin saves its API info on a blog-by-blog basis. For
 * use on a WP network, you should define the constants in wp-config.php to
 * avoid having to set the values manually on each blog.
 *
 * @return string $api_key
 */
function participad_api_key() {
	$api_key = '';

	if ( defined( 'PARTICIPAD_API_KEY' ) ) {
		$api_key = PARTICIPAD_API_KEY;
	} else {
		$api_key = get_option( 'ep_api_key' );
	}

	return $api_key;
}

/**
 * Returns the API endpoint, as set either in the DB or in a constant
 *
 * At the moment, the plugin saves its API info on a blog-by-blog basis. For
 * use on a WP network, you should define the constants in wp-config.php to
 * avoid having to set the values manually on each blog.
 *
 * @return string $api_endpoint
 */
function participad_api_endpoint() {
	$api_endpoint = '';

	if ( defined( 'PARTICIPAD_API_ENDPOINT' ) ) {
		$api_endpoint = PARTICIPAD_API_ENDPOINT;
	} else {
		$api_endpoint = get_option( 'ep_api_endpoint' );
	}

	return $api_endpoint;
}

/**
 * Is Participad installed correctly?
 */
function participad_is_installed_correctly() {
	$is_installed_correctly = true;

	$api_endpoint = participad_api_endpoint();
	$api_key      = participad_api_key();

	if ( ! $api_endpoint || ! $api_key ) {
		$is_installed_correctly = false;
	}

	if ( ! method_exists( participad_client(), 'is_connected' ) || ! participad_client()->is_connected() ) {
		$is_installed_correctly = false;
	}

	return $is_installed_correctly;
}
