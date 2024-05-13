<?php

/**
 * @package geslib
 */
namespace Inc\Geslib\Base;

use Inc\Dilve\Api\DilveApi;
use Inc\Geslib\Api\GeslibApiDbLinesManager;
use Inc\Geslib\Api\GeslibApiDbLoggerManager;
use Inc\Geslib\Api\GeslibApiDbLogManager;
use Inc\Geslib\Api\GeslibApiDbManager;
use Inc\Geslib\Api\GeslibApiDbProductsManager;
use Inc\Geslib\Api\GeslibApiDbQueueManager;
use Inc\Geslib\Api\GeslibApiLines;
use Inc\Geslib\Api\GeslibApiReadFiles;
use Inc\Geslib\Api\GeslibApiStoreData;

class Cron extends BaseController {

    public function register() {
        if ( ! wp_next_scheduled( 'geslib_cron_event' ) ) {
            wp_schedule_event( time(), 'daily', 'geslib_cron_event' );
        }
        add_action( 'geslib_cron_event', [ $this, 'geslib_cron_function' ] );
    }

    /**
     * geslib_cron_function
     *
     * @return void
     */
    function geslib_cron_function() {
        $geslibApiReadFiles = new GeslibApiReadFiles();
        $geslibApiLines = new GeslibApiLines();
        $geslibApiDbManager = new GeslibApiDbManager();
        $geslibApiDbLogManager = new GeslibApiDbLogManager;
        $geslibApiDbLinesManager = new GeslibApiDbLinesManager;
        $geslibApiDbProductsManager = new GeslibApiDbProductsManager;
        $geslibApiDbQueueManager = new GeslibApiDbQueueManager;
        $geslibApiDbLoggerManager = new GeslibApiDbLoggerManager;
        $geslibApiStoreData = new GeslibApiStoreData;
        $geslibApiReadFiles->readFolder();
        // Purge queues
        // Former calls to the cron may have stopped for some reason, before opening the next file.
        // Make sure the queues are processed before starting parsing more files.
        $queuetypes = ['store_lines', 'build_content', 'store_products', 'store_autors', 'store_categories', 'store_editorials', 'store_colecciones'];
        foreach( $queuetypes as $queuetype ) {
            $geslibApiDbQueueManager->processFromQueue( $queuetype );
            error_log('Processing previous queues: '. $queuetype);
        }
        while( $geslibApiDbLogManager->checkLoggedStatus() ) {
            $log_id = $geslibApiDbLogManager->getGeslibLoggedId();
            error_log('New process: '. $log_id);
            if ( !$geslibApiDbLogManager->isQueued() ){
                $geslibApiDbLogManager->setLogStatus( $log_id, 'queued' );
                error_log('Set log id: '. $log_id . ' to queued.');
            } else {
                $geslibApiDbQueueManager->deleteItemsFromQueue( 'store_lines' );
                error_log('Remove Store Lines');
            }
            $geslibApiLines->storeToLines($log_id);
            error_log('Store to lines');
            $geslibApiDbQueueManager->processFromQueue('store_lines');
            error_log('Store lines');
            $geslibApiDbQueueManager->processFromQueue('build_content');
            error_log('Build Content');
            $geslibApiDbQueueManager->processFromQueue( 'store_colecciones' );
            error_log('Store Colecciones');
            $geslibApiDbQueueManager->processFromQueue( 'store_editorials' );
            error_log('Store editorials');
            $geslibApiDbQueueManager->processFromQueue( 'store_autors' );
            error_log('Store autors');
            $geslibApiDbQueueManager->processFromQueue( 'store_categories' );
            error_log('Store categories');
            $geslibApiDbQueueManager->processFromQueue( 'store_products' );
            error_log('Store products');
            $geslibApiDbLinesManager->truncateGeslibLines();
            error_log('Truncate Geslib Lines');
            $geslibApiDbLogManager->setLogStatus( $log_id, 'processed');
            error_log('Set to processed');
        }
    }
}
