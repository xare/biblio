<?php
/**
 * @package Biblio
 */

 namespace Inc\Covers\Base;

 class BaseController
 {
  public $plugin_path;
  public $plugin_url;
  public $plugin;
  public $plugin_templates_path;

  public function __construct() {
    $this->plugin_path = plugin_dir_path( dirname( __FILE__, 3));
    $this->plugin_templates_path = plugin_dir_path( dirname( __FILE__, 3)).'/templates';
    $this->plugin_url = plugin_dir_url( dirname( __FILE__, 3));
    $this->plugin = plugin_basename( dirname( __FILE__, 3) ) . '/biblio.php';
  }

  public function activated(string $key)
  {
    $option = get_option('Dilve');
    return isset($option[$key]) ? $option[$key] : false;
  }
 }