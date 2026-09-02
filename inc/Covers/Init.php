<?php

namespace Inc\Covers;

use Inc\Covers\Base\Cron;
use Inc\Covers\Base\CoversLogController;
use Inc\Covers\Base\CoversScanProductsFormController;
use Inc\Covers\Base\Enqueue;
use Inc\Covers\Commands\CoversHelloCommand;
use Inc\Covers\Commands\CoversScanProductsCommand;
use Inc\Covers\Commands\CoversMediaCleanup;
use Inc\Covers\Pages\Dashboard;

final class Init
{
  /**
   * Store all the classes inside an array
   *
   * @return array Full list of classes
   */
  public static function get_services():Array {
    return [
      Dashboard::class,
      CoversLogController::class,
      CoversScanProductsCommand::class,
      /* CoversHelloCommand::class,
      CoversMediaCleanup::class, */
      CoversScanProductsFormController::class,
     /*  Enqueue::class,*/
      Cron::class,
    ];
  }


  /**
   * Loop through the classes, initialize them
   * and call the register() method if it exists
   *
   * @return void
   */
  public static function register_services() {
    self::ensure_covers_tables_schema();
    foreach(self::get_services() as $class){
      $service = self::instantiate( $class );
      if(method_exists($service,'register')) {
          $service->register();
      }
    }
  }

  /**
   * Ensure covers_lines table has required columns
   *
   * @return void
   */
  private static function ensure_covers_tables_schema() {
    global $wpdb;
    $covers_lines_table_name = $wpdb->prefix . 'covers_lines';
    
    // Check if table exists
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$covers_lines_table_name'" ) != $covers_lines_table_name ) {
      return;
    }

    // Add missing columns if they don't exist
    $columns = $wpdb->get_col( "DESC $covers_lines_table_name", 0 );
    
    if ( ! in_array( 'booktitle', $columns ) ) {
      $wpdb->query( "ALTER TABLE $covers_lines_table_name ADD COLUMN `booktitle` varchar(255)" );
    }
    
    if ( ! in_array( 'book_id', $columns ) ) {
      $wpdb->query( "ALTER TABLE $covers_lines_table_name ADD COLUMN `book_id` mediumint(9)" );
    }
  }
  /**
   * Initialize the class
   *
   * @param [type] $class class from the services array
   * @return class instance new instance of the class
   */
  private static function instantiate( $class ) {
    return new $class();
  }
}
