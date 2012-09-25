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
	define( 'WP_ETHERPAD_API_ENDPOINT', 'http://localhost:9001' );
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


######################################
################## ACTIONS HOOKS
######################################


/**
 * Applied to the HTML DIV created to house the rich text editor, prior to printing it on the screen. Filter function argument/return value is a string.
 */

/**
 * Applied to post content before putting it into a rich editor window.
 */
add_action('the_editor_content', 'etherpad_editor_content');
add_action('admin_menu', 'etherpad_register_admin_page');


/**
 * Runs just before the "advanced" section of the post editing form in the admin menus.
 */
#add_action('edit_form_advanced', 'etherpad_edit_form_advanced');



function etherpad_editor_content($content){
}

function etherpad_edit_form_advanced($content){
  echo $content;
}


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
