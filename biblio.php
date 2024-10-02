<?php
    /*
    Plugin Name: Biblio a plugin for libraries
    Description: A simple plugin for library management for WordPress
    Version: 1.0
    Author: xare xaresd@gmail.com
    */

defined( 'ABSPATH' ) or die ( 'Acceso prohibido');

// Require once the Composer Autoload
if ( file_exists( dirname( __FILE__).'/vendor/autoload.php' ) ){
    require_once dirname( __FILE__).'/vendor/autoload.php';
}

/**
 * The code that runs during plugin Activation
 *
 * @return void
 */
function activate_biblio(){
    Inc\Biblio\Base\Activate::activate();
    Inc\Geslib\Base\Activate::activate();
}
register_activation_hook( __FILE__, 'activate_biblio');

/**
 * The code that runs during plugin Deactivation
 * @return void
 */

 function deactivate_biblio() {
    Inc\Biblio\Base\Deactivate::deactivate();
 }

 register_deactivation_hook( __FILE__, 'deactivate_biblio' );

 if ( class_exists( 'Inc\\Biblio\\Init' ) ) {
    Inc\Biblio\Init::register_services();
 }
 if ( class_exists( 'Inc\\Geslib\\Init' ) ) {
    Inc\Geslib\Init::register_services();
 }
 if ( class_exists( 'Inc\\Covers\\Init' ) ) {
    Inc\Covers\Init::register_services();
 }

/*  add_action('admin_init', function() {
   if (isset($_GET['run_cron_setup']) && $_GET['run_cron_setup'] == '1') {
       $cron = new \Inc\Geslib\Base\Cron();
       $cron->register();
       echo "Cron jobs have been registered.";
   }
}); */
