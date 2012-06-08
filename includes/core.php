<?php
// most of the plugin infrastucture will reside here until
// I find a better way to organize it


/*
 * Will use the Etherpad lite client API found here: https://github.com/TomNomNom/etherpad-lite-client
 * version: not specified heh
 */
require 'etherpad-lite-client.php';

class WP_Etherpad{

	public $action; # edit, new
	public $ep_id; # if editing new post, will hold the id here

	function &init() {
		static $instance = false;

		if ( empty( $instance ) ) {
			$instance = new WP_Etherpad;
		}

		return $instance;
	}

	function __construct(){
		if ( !$this->load_on_page() ) {
			return;
		}

		$ep_instance = new EtherpadLiteClient(
			WP_ETHERPAD_API_KEY,
			WP_ETHERPAD_API_ENDPOINT
		);

		$this->set_action();
		$this->set_post();


		add_action('the_editor', array( &$this, 'editor' ));
	}

	/**
	 * Will an Etherpad instance appear on this page?
	 *
	 * No need to initialize the API client on every pageload
	 *
	 * @todo Implement
	 *
	 * @return bool
	 */
	function load_on_page() {
		return true;
	}

	/**
	 * Will return the link in the form of { http://podservice.tld/p/{:pod_iod}?query }
	 *
	 * @return (string)
	 */
	public function construct_link(){}
	public function link(){}

	function editor() {
		echo "<iframe src='http://localhost:9001/p/" . $this->ep_id . "?showControls=true&showChat=true&showLineNumbers=true&useMonospaceFont=false' width=100% height=400>";

	}

  /**
   * get_action
   *
   * @return (string)
   */
  public function get_action(){
    return $this->action;
  }

  /**
   * get_post
   *
   * Will return the post id if the current action is edit
   * @return (post_id)
   */
  public function get_post(){}

  /**
   * set_action
   *
   * @param (string) (url) - in the form of (/path/to/wordpress)?action=edit&post=3
   * @return nothing
   */
  private function set_action(){
    $this->action = isset($_GET['action']) ? $_GET['action'] : false;
  }

	/**
	 * set_post
	 *
	 * Will set the current post id if action == edit
	 *
	 * @param (int) (post_id) - the current font id
	 */
	private function set_post(){
		global $post;

		$post_id = 0;

		if ( isset( $_GET['post'] ) ) {
			$post_id = $_GET['post'];
		} else if ( !empty( $post->ID ) ) {
			$post_id = $post->ID;
		}

		$this->ep_id = wp_hash( $post_id );
	}


}

?>
