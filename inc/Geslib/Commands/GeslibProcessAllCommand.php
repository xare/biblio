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
            if ( !$log_id ) {
                WP_CLI::line( 'No valid log_id found. Stopping.' );
                break;
            }
            $geslibApiDbLogManager->setLogStatus( $log_id, 'queued' );

            // Skip re-parse if queue already has items for this log
            if ( ! $geslibApiDbQueueManager->hasQueueItemsForLog( $log_id ) ) {
                WP_CLI::line( "Log {$log_id}: parsing file and creating queue items." );
                $geslibApiLines->storeToLines($log_id);
            } else {
                WP_CLI::line( "Log {$log_id}: resuming from existing queue items (skip re-parse)." );
            }
            
            WP_CLI::line( "Log {$log_id}: processing store_lines queue." );
            $geslibApiDbQueueManager->processFromQueue( 'store_lines' );
            $geslibApiStoreData->storeAuthors();
            $geslibApiStoreData->storeEditorials();
            $geslibApiDbProductsManager->storeProducts();

            WP_CLI::line( "Log {$log_id}: processing remaining queues." );
            $geslibApiDbQueueManager->processFromQueue( 'store_products' );
            $geslibApiDbQueueManager->processFromQueue( 'store_editorials' );
            $geslibApiDbQueueManager->processFromQueue( 'store_autors' );
            $geslibApiDbQueueManager->processFromQueue( 'store_categories' );

            $geslibApiDbLinesManager->truncateGeslibLines();
            $geslibApiDbLogManager->setLogStatus( $log_id, 'processed');
            $processedFilename = $geslibApiDbLogManager->getGeslibLoggedFilename( $log_id );
            if ( $processedFilename ) {
                $geslibApiReadFiles->moveToProcessed( $processedFilename );
            }
            WP_CLI::line( "Log {$log_id}: completed." );
        }
        WP_CLI::line( 'The process is over.' );
    }
}