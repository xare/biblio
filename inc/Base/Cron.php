<?php

/**
 * @package biblio
 */
namespace Inc\Biblio\Base;

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
        foreach(['geslib','covers'] as $service) {
            if ( ! wp_next_scheduled( $service.'_cron_event' ) ) {
                wp_schedule_event( time(), 'daily', $service.'_cron_event' );
            }
            add_action( $service.'_cron_event', [ $this, $service.'_cron_function' ] );
        }
    }
    /**
     * geslib_cron_function
     *
     * @return void
     */
    function geslib_cron_function() {
        $geslibApiReadFiles = new GeslibApiReadFiles;
        $geslibApiLines = new GeslibApiLines;
        $geslibApiDbLogManager = new GeslibApiDbLogManager;
        $geslibApiDbLinesManager = new GeslibApiDbLinesManager;
        $geslibApiDbQueueManager = new GeslibApiDbQueueManager;
        $geslibApiDbLoggerManager = new GeslibApiDbLoggerManager;
        $geslibApiReadFiles->readFolder();
        // Purge queues
        // Former calls to the cron may have stopped for some reason, before opening the next file.
        // Make sure the queues are processed before starting parsing more files.
        $queuetypes = ['store_products', 'build_content', 'store_autors', 'store_categories', 'store_editorials', 'store_lines', 'store_colecciones'];
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
            foreach( $queuetypes as $queuetype ) {
                $geslibApiDbQueueManager->processFromQueue( $queuetype );
                error_log('Process '.$queuetype );
            }
            $geslibApiDbLinesManager->truncateGeslibLines();
            error_log('Truncate Geslib Lines');
            $geslibApiDbLogManager->setLogStatus( $log_id, 'processed');
            error_log('Set to processed');
        }
    }
    /**
     * covers_cron_function
     *
     * @return void
     */
    function covers_cron_function() {

    }
}