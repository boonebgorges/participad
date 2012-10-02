<?php
/*
Plugin Name: Participad
Plugin URI: http://participad.com
Description: Real time collaboration in WordPress, through integration with an Etherpad Lite installation
Version: 1.0-bleeding
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

				include( PARTICIPAD_PLUGIN_DIR . 'modules/' . $file );

				// Strip '.php'
				$class_name = array_pop( array_reverse( explode( '.', $file ) ) );

				// Drop 'class-'
				$class_name = substr( $class_name, 6 );

				// Convert to uppercase + underscores
				$class_name = implode( '_', array_map( 'ucwords', explode( '-', $class_name ) ) );

				if ( class_exists( $class_name ) ) {
					$this->modules[ $class_name ] = new $class_name;
				}
			}
		}
	}
}

/**
 * Plugin bootstrap
 */
function participad_bootstrap() {
	Participad::instance();
}
add_action( 'init', 'participad_bootstrap' );
