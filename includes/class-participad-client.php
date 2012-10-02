<?php

if ( ! class_exists( 'EtherpadLiteClient' ) ) {
	require PARTICIPAD_PLUGIN_DIR . 'lib/etherpad-lite-client.php';
}

/**
 * Participad Etherpad client
 *
 * Our extension of the EtherpadLiteClient base class. Reworked a bit so as to
 * function as a singleton
 *
 * @since 1.0
 */
class Participad_Client extends EtherpadLiteClient {
	protected static $instance;

	/**
	 * Ensures that we've got a singleton
	 */
	public static function instance() {
		if ( empty( self::$instance ) ) {
			self::$instance = new Participad_Client(
				participad_api_key(),
				participad_api_endpoint()
			);
		}

		return self::$instance;
	}
}
