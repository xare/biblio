<?php
/**
 * @package biblio
 */
namespace Inc\Biblio\Base;

use Inc\Biblio\Base\BaseController;
/**
 * SettingsLinks
 * Adds links under the plugin name in the plugin settings page.
 */
class SettingsLinks extends BaseController
{
  /**
   * register
   *
   * @return void
   */
  public function register(){
    add_filter( 'plugin_action_links_'.$this->plugin, [ $this, 'settings_link' ]);
  }

  /**
   * settings_link
   *
   * @param  array $links
   * @return array
   */
  public function settings_link($links): array {
    $settings_link = '<a href="admin.php?page=biblio">Settings</a>';
    array_push($links, $settings_link);
    return $links;
  }

 }