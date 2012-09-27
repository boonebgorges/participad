<?php

if ( ! class_exists( 'EtherpadLiteClient' ) ) {
	require WPEP_PLUGIN_DIR . 'lib/etherpad-lite-client.php';
}

/**
 * WP Etherpad client
 *
 * Our extension of the EtherpadLiteClient base class. Reworked a bit so as to
 * function as a singleton
 *
 * @since 1.0
 */
class WPEP_Client extends EtherpadLiteClient {
	protected static $instance;

	/**
	 * Ensures that we've got a singleton
	 */
	public static function instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new WPEP_Client(
				wpep_api_key(),
				wpep_api_endpoint()
			);
		}

		return self::$instance;
	}
}
