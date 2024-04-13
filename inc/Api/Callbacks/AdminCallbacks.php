<?php
/**
 * @package starterkit
 */

 namespace Inc\Biblio\Api\Callbacks;

use Inc\Biblio\Base\BaseController;

 class AdminCallbacks extends BaseController {

  public function adminDashboard() {
    return require_once("$this->plugin_templates_path/adminDashboard.php");
  }
 }