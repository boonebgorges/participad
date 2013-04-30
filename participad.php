<?php
/*
Plugin Name: Participad
Plugin URI: http://participad.org
Description: Real time collaboration in WordPress, through integration with an Etherpad Lite installation
Version: 1.0.2
Author: Boone B Gorges
Author URI: http://boone.gorg.es
*/

class Participad {
	function instance() {
		static $instance;

		if ( empty( $instance ) ) {
			$instance = new Participad;
		}

		return $instance;
	}

	function __construct() {
		$this->setup_constants();
		$this->includes();
		$this->load_modules();

		// Plugins should load themselves here
		do_action( 'participad_init', $this );
	}

	/**
	 * Define some constants
	 *
	 * These are provided primarily because it's necessary on some setups
	 * to override WP's stupid plugin_dir_path() etc because of symlinks.
	 */
	function setup_constants() {
		if ( ! defined( 'PARTICIPAD_PLUGIN_DIR' ) ) {
			define( 'PARTICIPAD_PLUGIN_DIR', trailingslashit( dirname(__FILE__) ) );
		}

		if ( ! defined( 'PARTICIPAD_PLUGIN_URL' ) ) {
			define( 'PARTICIPAD_PLUGIN_URL', WP_PLUGIN_URL . '/participad/' );
		}
	}

	/**
	 * Require necessary files
	 */
	function includes() {
		require PARTICIPAD_PLUGIN_DIR . 'includes/functions.php';
		require PARTICIPAD_PLUGIN_DIR . 'includes/class-participad-integration.php';
		require PARTICIPAD_PLUGIN_DIR . 'includes/class-participad-user.php';
		require PARTICIPAD_PLUGIN_DIR . 'includes/class-participad-post.php';
		require PARTICIPAD_PLUGIN_DIR . 'includes/class-participad-client.php';

		if ( is_admin() ) {
			require PARTICIPAD_PLUGIN_DIR . 'includes/admin.php';
		}
	}

	/**
	 * Load the modules packaged with Participad
	 *
	 * @todo How will these be toggled? In the modules themselves?
	 */
	function load_modules() {
		if ( $modules_dir = opendir( PARTICIPAD_PLUGIN_DIR . 'modules' ) ) {
			while ( ( $file = readdir( $modules_dir ) ) !== false ) {
				if ( '.' == substr( $file, 0, 1 ) ) {
					continue;
				}

				if ( ! include( PARTICIPAD_PLUGIN_DIR . 'modules/' . $file . '/' . $file . '.php' ) ) {
					continue;
				}

				// Build class name
				$class_name = 'Participad_Integration_' . ucwords( $file );

				if ( class_exists( $class_name ) ) {
					$this->modules[ $file ] = new $class_name;
				}
			}
		}
	}
}

/**
 * Plugin bootstrap
 */
function participad() {
	return Participad::instance();
}
add_action( 'plugins_loaded', 'participad' );

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
