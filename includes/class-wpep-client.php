<?php

if ( ! class_exists( 'EtherpadLiteClient' ) ) {
	require WP_ETHERPAD_PLUGIN_DIR . "lib/etherpad-lite-client.php";
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
	 * @todo Remove references to constants
	 */
	public static function instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new WPEP_Client(
				WP_ETHERPAD_API_KEY,
				WP_ETHERPAD_API_ENDPOINT
			);
		}

		return self::$instance;
	}
}
