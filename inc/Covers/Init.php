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
      CoversHelloCommand::class,
      CoversMediaCleanup::class,
      CoversScanProductsFormController::class,
      Enqueue::class,
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
    foreach(self::get_services() as $class){
      $service = self::instantiate( $class );
      if(method_exists($service,'register')) {
          $service->register();
      }
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
