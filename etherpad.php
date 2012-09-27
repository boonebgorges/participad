<?php
/*
Plugin Name: EtherPad For Wordpress
Plugin URI: http://etherpad.org
Description: Enable real time collaboration on WordPress content by integrating with an Etherpad Lite installation
Version: 1.0-bleeding
Author: Boone B Gorges
Author URI: http://boone.gorg.es
*/

class WPEP {
	function instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new WPEP;
		}

		return $instance;
	}

	function __construct() {
		$this->setup_constants();
		$this->includes();
		$this->do_integration();
	}

	/**
	 * Define some constants
	 *
	 * These are provided primarily because it's necessary on some setups
	 * to override WP's stupid plugin_dir_path() etc because of symlinks.
	 */
	function setup_constants() {
		if ( ! defined( 'WPEP_PLUGIN_DIR' ) ) {
			define( 'WPEP_PLUGIN_DIR', trailingslashit( dirname(__FILE__) ) );
		}

		if ( ! defined( 'WPEP_PLUGIN_URL' ) ) {
			define( 'WPEP_PLUGIN_URL', WP_PLUGIN_URL . '/etherpad-wordpress/' );
		}
	}

	/**
	 * Require necessary files
	 */
	function includes() {
		require WPEP_PLUGIN_DIR . 'includes/functions.php';
		require WPEP_PLUGIN_DIR . 'includes/class-wpep-integration.php';
		require WPEP_PLUGIN_DIR . 'includes/class-wpep-user.php';
		require WPEP_PLUGIN_DIR . 'includes/class-wpep-post.php';
		require WPEP_PLUGIN_DIR . 'includes/class-wpep-client.php';

		if ( is_admin() ) {
			require WPEP_PLUGIN_DIR . 'includes/admin.php';
		}
	}

	function do_integration() {
		$this->integration = new WPEP_Integration;
	}

}

/**
 * Plugin bootstrap
 */
function wpep_bootstrap() {
	WPEP::instance();
}
add_action( 'init', 'wpep_bootstrap' );
