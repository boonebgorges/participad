<?php

/**
 * Utility function for referencing the API client singleton
 *
 * For example:
 *
 *     $text = wpep_client()->getText( $ep_post_id );
 *
 * @since 1.0
 */
function wpep_client() {
	return WPEP_Client::instance();
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
function wpep_api_key() {
	$api_key = '';

	if ( defined( 'WPEP_API_KEY' ) ) {
		$api_key = WPEP_API_KEY;
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
function wpep_api_endpoint() {
	$api_endpoint = '';

	if ( defined( 'WPEP_API_ENDPOINT' ) ) {
		$api_endpoint = WPEP_API_ENDPOINT;
	} else {
		$api_endpoint = get_option( 'ep_api_endpoint' );
	}

	return $api_endpoint;
}
