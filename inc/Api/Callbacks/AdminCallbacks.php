<?php
/**
 * @package biblio
 */

namespace Inc\Biblio\Api\Callbacks;

use Inc\Biblio\Base\BaseController;

 class AdminCallbacks extends BaseController {

  public function adminDashboard() {
    return require_once("$this->plugin_templates_path/adminDashboard.php");
  }
  public function adminCustomPostType() {
    return require_once("$this->plugin_templates_path/adminCustomPostType.php");
  }
  public function adminTaxonomy(){
    return require_once("$this->plugin_templates_path/adminTaxonomy.php");
  }

  public function biblioOptionsGroup($input) {
    return $input;
  }

  public function biblioAdminSection() {
    echo "visit this section";
  }
  public function biblioTextExample() {
    $value = esc_attr(get_option( 'text_example' ));
    echo '<input
      type="text"
      class="regular-text"
      name="text-example"
      value="'.$value.'"
      placeholder="Text Example">';
  }
  public function biblioFirstName() {
    $value = esc_attr(get_option( 'first_name' ));
    echo '<input
            type="text"
            class="regular-text"
            name="first-name"
            value="'.$value.'"
            placeholder="First Name">';
  }
 }