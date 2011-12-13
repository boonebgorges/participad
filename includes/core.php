<?php
// most of the plugin infrastucture will reside here until 
// I find a better way to organize it


/*
 * Will use the Etherpad lite client API found here: https://github.com/TomNomNom/etherpad-lite-client
 * version: not specified heh 
 */  
require 'etherpad-lite-client.php';
  
class Etherpad{

  public $action; # edit, new
  public $post; # if editing new post, will hold the id here

  function __construct(){
    $this->set_action();
    $this->set_post();
  }


  /**
   * Will return the link in the form of { http://podservice.tld/p/{:pod_iod}?query }
   *
   * @return (string)
   */
  public function construct_link(){}
  public function link(){}

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
    $this->post = (isset($_GET['post']) ? $_GET['post'] : false ); 
  }

}

?>
