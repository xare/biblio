<?php

namespace Inc\Biblio;

use Inc\Biblio\Base\Enqueue;
use Inc\Biblio\Pages;

 final class init
 {
    /**
     * Store all the classes inside an array
     *
     * @return array Full list of classes
     */
    public static function get_services(): array {
        return [
            Base\Enqueue::class,
            Pages\Dashboard::class,
            Base\SettingsLinks::class,
            Base\CustomPostTypeController::class,
            Base\CustomTaxonomyController::class,
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
            if(method_exists($service, 'register')) {
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