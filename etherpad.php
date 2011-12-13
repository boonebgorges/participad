<?php
/*
Plugin Name: EtherPad For Wordpress
Plugin URI: http://etherpad.org
Description: Replaces the default Wordpress editor with <a href="http://etherpad.org/"> Etherpad</a>. Allowing authors to colaborate on the same post.
Version: 0.1.1
Author: Robert Zimtea
Author URI: http://www.robert.zimtea.com/
*/

require_once "includes/core.php";

// instantiante the etherpad client
// TODO: will need to make this available for change inside the settings panel
$instance = new EtherpadLiteClient('EtherpadFTW,http://beta.etherpad.org/api');
$etherpad = new Etherpad;


######################################
################## ACTIONS HOOKS
######################################


/**
 * Applied to the HTML DIV created to house the rich text editor, prior to printing it on the screen. Filter function argument/return value is a string.
 */
add_action('the_editor', 'etherpad_editor');

/**
 * Applied to post content before putting it into a rich editor window.
 */
add_action('the_editor_content', 'etherpad_editor_content');
add_action('admin_menu', 'etherpad_register_admin_page');


/**
 * Runs just before the "advanced" section of the post editing form in the admin menus.
 */
#add_action('edit_form_advanced', 'etherpad_edit_form_advanced');

function etherpad_editor($editor){
  echo "<iframe src='http://beta.etherpad.org/p/123123312?showControls=true&showChat=true&showLineNumbers=true&useMonospaceFont=false' width=100% height=400>";
}

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
