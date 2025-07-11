<?php
/**
* WP-CLI Commands
*/

namespace Inc\Geslib\Commands;

use Inc\Geslib\Api\GeslibApiDbLinesManager;
use Inc\Geslib\Api\GeslibApiDbLogManager;
use Inc\Geslib\Api\GeslibApiDbManager;
use Inc\Geslib\Api\GeslibApiDbProductsManager;
use Inc\Geslib\Api\GeslibApiDbQueueManager;
use Inc\Geslib\Api\GeslibApiLines;
use Inc\Geslib\Api\GeslibApiReadFiles;
use Inc\Geslib\Api\GeslibApiStoreData;
use WP_CLI;

class GeslibProcessAllCommand {
    public function register() {
        if ( class_exists( 'WP_CLI' ) ) {
            WP_CLI::add_command( 'geslib processAll', [ $this, 'execute' ], [
              'synopsis' => [
                    [
                        'type'        => 'flag',
                        'name'        => 'process-all',
                        'description' => 'Realise all the process.',
                        'optional'    => true,
                    ],
                ],
            ]);
        }
    }

    /**
    * Process all products, categories and authors.
    *
    * ## OPTIONS
    *
    * [--name=<name>]
    * : The name of the person to greet.
    *
    * ## EXAMPLES
    *
    * wp geslib processAll
    * wp geslib processAll --process-store-products
    * @when after_wp_load
    */
    public function execute ($args, $assoc_args) {
        $geslibApiDbLogManager = new GeslibApiDbLogManager;
        $geslibApiDbLinesManager = new GeslibApiDbLinesManager;
        $geslibApiDbQueueManager = new GeslibApiDbQueueManager;
        $geslibApiDbProductsManager = new GeslibApiDbProductsManager;
        $geslibApiLines = new GeslibApiLines;
        $geslibApiStoreData = new GeslibApiStoreData;
        $geslibApiReadFiles = new GeslibApiReadFiles;
        $geslibApiReadFiles->readFolder();
        while ( $geslibApiDbLogManager->checkLoggedStatus() ) {
            $log_id = $geslibApiDbLogManager->getGeslibLoggedId();
            if ( !$geslibApiDbLogManager->isQueued() ){
                $geslibApiDbLogManager->setLogStatus( $log_id, 'queued' );
            } else {
                $geslibApiDbQueueManager->deleteItemsFromQueue( 'store_lines' );
            }
            WP_CLI::line( 'Enviamos las lineas a la cola de proceso. ');
            $stored_lines = $geslibApiLines->storeToLines($log_id);
            
            WP_CLI::line( 'Procesamos las lineas de la cola en modo store_lines. ');
            $geslibApiDbQueueManager->processFromQueue( 'store_lines' );
            $geslibApiStoreData->storeAuthors();
            $geslibApiStoreData->storeEditorials();
            WP_CLI::line( 'Enviamos los productos a la cola de proceso. ');
            $geslibApiDbProductsManager->storeProducts();

            WP_CLI::line( 'Empezamos a procesar los productos y a guardarlos en la tienda. ');
            $geslibApiDbQueueManager->processFromQueue( 'store_products' );

            $geslibApiDbQueueManager->processFromQueue( 'store_editorials' );
            $geslibApiDbQueueManager->processFromQueue( 'store_autors' );
            $geslibApiDbQueueManager->processFromQueue( 'store_categories' );

            WP_CLI::line( 'Se ha terminado de procesar la cola store_products. ');
            $geslibApiDbLinesManager->truncateGeslibLines();

            WP_CLI::line( 'Geslib Lines ha sido borrado. ');
            $geslibApiDbLogManager->setLogStatus( $log_id, 'processed');
            WP_CLI::line( 'Archivo en geslib log marcado como procesado para log_id. '.$log_id);
        }
        WP_CLI::line( 'The process is over. ');
    }
}