<?php

/**
 * @package geslib
 */
namespace Inc\Geslib\Base;

use Inc\Biblio\Api\BiblioApi;
use Inc\Geslib\Api\GeslibApiDbLinesManager;
use Inc\Geslib\Api\GeslibApiDbLogManager;
use Inc\Geslib\Api\GeslibApiDbManager;
use Inc\Geslib\Api\GeslibApiDbProductsManager;
use Inc\Geslib\Api\GeslibApiDbQueueManager;
use Inc\Geslib\Api\GeslibApiDbTaxonomyManager;
use Inc\Geslib\Api\GeslibApiLines;
use Inc\Geslib\Api\GeslibApiReadFiles;
use Inc\Geslib\Api\GeslibApiStoreData;

class Cron extends BaseController {

    public function register() {
        if ( ! wp_next_scheduled( 'geslib_cron_event' ) ) {
            wp_schedule_event( time(), 'twohourly', 'geslib_cron_event' );
        }
        if ( ! wp_next_scheduled( 'geslib_removeUncategorized_cron_event' ) ) {
            wp_schedule_event( time(), 'twohourly', 'geslib_removeUncategorized_cron_event' );
        }
        add_action( 'geslib_cron_event', [ $this, 'geslib_cron_function' ] );
        add_action( 'geslib_removeUncategorized_cron_event', [ $this, 'geslib_remove_uncategorized_cron_function' ] );
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
        $geslibApiStoreData = new GeslibApiStoreData;
        $biblioApi = new BiblioApi;
        $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'COMIENZA EL CRON', 'geslib');
        $geslibApiReadFiles->readFolder();
        // Purge queues
        // Former calls to the cron may have stopped for some reason, before opening the next file.
        // Make sure the queues are processed before starting parsing more files.
        $queuetypes = [
            'store_lines',
            'build_content',
            'store_products',
            'store_autors',
            'store_categories',
            'store_editorials',
            'store_colecciones',
        ];
        foreach( $queuetypes as $queuetype ) {
            $geslibApiDbQueueManager->processFromQueue( $queuetype );
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Processing previous queues: '. $queuetype, 'geslib');
        }
        while( $geslibApiDbLogManager->checkLoggedStatus() ) {
            $log_id = $geslibApiDbLogManager->getGeslibLoggedId();
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'New process: '. $log_id, 'geslib', 'geslib');
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
                $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Processing previous queues: '. $queuetype, 'geslib');
            }
            $geslibApiDbLinesManager->truncateGeslibLines();
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Truncate Geslib Lines', 'geslib');
            $geslibApiDbLogManager->setLogStatus( $log_id, 'processed');
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Set log id: '. $log_id . ' to processed.', 'geslib');
        }
    }

    function geslib_remove_uncategorized_cron_function() {
        $geslibApiDbTaxonomyManager = new GeslibApiDbTaxonomyManager;
        $biblioApi = new BiblioApi();
        $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Start process', 'geslib');
        $geslibApiDbTaxonomyManager->removeUncategorizedCategory();
    }
    
}
