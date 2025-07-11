<?php

/**
 * @package biblio
 */

namespace Inc\Biblio\Base;
use Inc\Biblio\Base\BaseController;

class Enqueue extends BaseController {
  public function register(){
    add_action ( 'admin_enqueue_scripts', [$this, 'enqueue_admin']);
  }

  function enqueue_admin() {
        // enqueue all our scripts
        wp_enqueue_style('BiblioAdminStyle', $this->plugin_url .'dist/css/biblio.min.css');
        wp_enqueue_script('BiblioAdminScript', $this->plugin_url .'dist/js/biblio.min.js');
        wp_enqueue_style('CoversAdminStyle', $this->plugin_url .'dist/css/covers.min.css');
        wp_enqueue_script('CoversAdminScript', $this->plugin_url .'dist/js/covers.min.js');
      }
}