<?php

/**
 * @package geslib
 */
namespace Inc\Geslib\Base;

use Inc\Geslib\Api\GeslibApi;
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
        $geslibApi = new GeslibApi();
        $geslibApiReadFiles = new GeslibApiReadFiles();
        $geslibApiLines = new GeslibApiLines();
        $geslibApiDbManager = new GeslibApiDbManager();
        $geslibApiDbLogManager = new GeslibApiDbLogManager;
        $geslibApiDbLinesManager = new GeslibApiDbLinesManager;
        $geslibApiDbProductsManager = new GeslibApiDbProductsManager;
        $geslibApiDbQueueManager = new GeslibApiDbQueueManager;
        $geslibApiStoreData = new GeslibApiStoreData;
        $geslibApiReadFiles->readFolder();
        // Purge queues
        // Former calls to the cron may have stopped for some reason, before opening the next file.
        // Make sure the queues are processed before starting parsing more files.
        $queuetypes = ['store_lines', 'build_content', 'store_products', 'store_autors', 'store_categories', 'store_editorials', 'store_colecciones'];
        foreach( $queuetypes as $queuetype ) {
            $geslibApiDbQueueManager->processFromQueue( $queuetype );
            $geslibApi->biblio_debug_log('Geslib Cron', 'Processing previous queues: '. $queuetype);
        }
        while( $geslibApiDbLogManager->checkLoggedStatus() ) {
            $log_id = $geslibApiDbLogManager->getGeslibLoggedId();
            $geslibApi->biblio_debug_log('Geslib Cron', 'New process: '. $log_id);
            if ( !$geslibApiDbLogManager->isQueued() ){
                $geslibApiDbLogManager->setLogStatus( $log_id, 'queued' );
                $geslibApi->biblio_debug_log('Geslib Cron', 'Set log id: '. $log_id . ' to queued.');
            } else {
                $geslibApiDbQueueManager->deleteItemsFromQueue( 'store_lines' );
                $geslibApi->biblio_debug_log('Geslib Cron', 'Remove Store Lines');
            }
            $geslibApiLines->storeToLines($log_id);
            $geslibApi->biblio_debug_log('Geslib Cron', 'Store to lines');
            $geslibApiDbQueueManager->processFromQueue('store_lines');
            $geslibApi->biblio_debug_log('Geslib Cron', 'Store lines');
            $geslibApiDbQueueManager->processFromQueue('build_content');
            $geslibApi->biblio_debug_log('Geslib Cron', 'Build Content');
            $geslibApiDbQueueManager->processFromQueue( 'store_colecciones' );
            $geslibApi->biblio_debug_log('Geslib Cron', 'Store Colecciones');
            $geslibApiDbQueueManager->processFromQueue( 'store_editorials' );
            $geslibApi->biblio_debug_log('Geslib Cron', 'Store editorials');
            $geslibApiDbQueueManager->processFromQueue( 'store_autors' );
            $geslibApi->biblio_debug_log('Geslib Cron', 'Store autors');
            $geslibApiDbQueueManager->processFromQueue( 'store_categories' );
            $geslibApi->biblio_debug_log('Geslib Cron', 'Store categories');
            $geslibApiDbQueueManager->processFromQueue( 'store_products' );
            $geslibApi->biblio_debug_log('Geslib Cron', 'Store products');
            $geslibApiDbLinesManager->truncateGeslibLines();
            $geslibApi->biblio_debug_log('Geslib Cron', 'Truncate Geslib Lines');
            $geslibApiDbLogManager->setLogStatus( $log_id, 'processed');
            $geslibApi->biblio_debug_log('Geslib Cron', 'Set to processed');
        }
    }

    function geslib_remove_uncategorized_cron_function() {
        $geslibApiDbTaxonomyManager = new GeslibApiDbTaxonomyManager;
        $geslibApi = new GeslibApi();
        $geslibApi->biblio_debug_log('Geslib Uncategorized Cron', 'Start process');
        $geslibApiDbTaxonomyManager->removeUncategorizedCategory();
    }
    
}
