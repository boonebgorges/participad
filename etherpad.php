<?php
/*
Plugin Name: EtherPad For Wordpress
Plugin URI: http://etherpad.org
Description: Enable real time collaboration on WordPress content by integrating with an Etherpad Lite installation
Version: 0.1.1
Author: Boone B Gorges
Author URI: http://boone.gorg.es
*/

// Set up details for Etherpad client
// Temporary
if ( !defined( 'WP_ETHERPAD_API_ENDPOINT' ) ) {
	define( 'WP_ETHERPAD_API_ENDPOINT', 'http://boone.cool:9001' );
}

if ( !defined( 'WP_ETHERPAD_API_KEY' ) ) {
	define( 'WP_ETHERPAD_API_KEY', 'URAohyQdX6v7veTGM3Gw5sKUEow8zb2C' );
}

// @todo plugin_dir() sucks
define( 'WP_ETHERPAD_PLUGIN_DIR', trailingslashit( dirname(__FILE__) ) );


/**
 * Plugin bootstrap
 */
function wp_etherpad_bootstrap() {
	require WP_ETHERPAD_PLUGIN_DIR . "includes/core.php";
	WP_Etherpad::init();
}
add_action( 'init', 'wp_etherpad_bootstrap' );

/**
 * Adds the etherpad under the Settings in WPAdmin
 */
function etherpad_register_admin_page() {
	add_submenu_page( 'options-general.php', 'Etherpad', 'Etherpad Settings', 'manage_options', 'my-custom-submenu-page', 'etherpad_admin_page' );
}

function etherpad_admin_page() {
  include 'includes/admin_page.php';
}

?>
