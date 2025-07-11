<?php

/**
 * @package biblio
 */
namespace Inc\Biblio\Base;

use Inc\Biblio\Api\BiblioApi;
use Inc\Geslib\Api\GeslibApiDbLinesManager;
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
        $biblioApi = new BiblioApi;
        $geslibApiReadFiles->readFolder();
        // Purge queues
        // Former calls to the cron may have stopped for some reason, before opening the next file.
        // Make sure the queues are processed before starting parsing more files.
        $queuetypes = [
            'store_lines', 
            'build_content', 
            'store_autors', 
            'store_categories', 
            'store_editorials',  
            'store_colecciones', 
            'store_products'
        ];
        foreach( $queuetypes as $queuetype ) {
            $geslibApiDbQueueManager->processFromQueue( $queuetype );
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Processing previous queues: '. $queuetype, 'geslib');
        }
        while( $geslibApiDbLogManager->checkLoggedStatus() ) {
            $log_id = $geslibApiDbLogManager->getGeslibLoggedId();
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'New process: '. $log_id, 'geslib');
            if ( !$geslibApiDbLogManager->isQueued() ){
                $geslibApiDbLogManager->setLogStatus( $log_id, 'queued' );
                $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Set log id: '. $log_id . ' to queued.', 'geslib');
            } else {
                $geslibApiDbQueueManager->deleteItemsFromQueue( 'store_lines' );
                $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Delete items from queue: store_lines', 'geslib');
            }
            $geslibApiLines->storeToLines($log_id);
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Store to lines', 'geslib');
            foreach( $queuetypes as $queuetype ) {
                $geslibApiDbQueueManager->processFromQueue( $queuetype );
                $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Processing queues: '. $queuetype, 'geslib');
            }
            $geslibApiDbLinesManager->truncateGeslibLines();
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Truncate Geslib Lines', 'geslib');
            $geslibApiDbLogManager->setLogStatus( $log_id, 'processed');
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Set log id: '. $log_id . ' to processed.', 'geslib');
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