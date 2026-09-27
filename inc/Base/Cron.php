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
        
        $cronStart = microtime(true);
        $filesProcessed = 0;
        
        // Phase 1: Read files
        $phaseStart = microtime(true);
        $geslibApiReadFiles->readFolder();
        $phaseElapsed = round(microtime(true) - $phaseStart, 1);
        $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Phase READ FILES completed in {$phaseElapsed}s", 'geslib');

        // Phase 2: Process previous queues
        $queuetypes = [
            'store_lines', 
            'build_content', 
            'store_autors', 
            'store_categories', 
            'store_editorials',  
            'store_colecciones', 
            'store_products'
        ];
        $phaseStart = microtime(true);
        foreach( $queuetypes as $queuetype ) {
            $geslibApiDbQueueManager->processFromQueue( $queuetype );
        }
        $phaseElapsed = round(microtime(true) - $phaseStart, 1);
        $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Phase PREVIOUS QUEUES completed in {$phaseElapsed}s", 'geslib');

        // Phase 3: Stuck log recovery
        $stuckLogId = $geslibApiDbLogManager->getQueuedLogId();
        if ($stuckLogId) {
            $phaseStart = microtime(true);
            $geslibApiDbLogManager->setLogStatus( $stuckLogId, 'queued' );
            $geslibApiDbQueueManager->deleteItemsFromQueue( 'store_lines' );
            $geslibApiLines->storeToLines($stuckLogId);
            foreach( $queuetypes as $queuetype ) {
                $geslibApiDbQueueManager->processFromQueue( $queuetype );
            }
            $geslibApiDbLinesManager->truncateGeslibLines();
            $geslibApiDbLogManager->setLogStatus( $stuckLogId, 'processed');
            $phaseElapsed = round(microtime(true) - $phaseStart, 1);
            $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Phase STUCK RECOVERY (log {$stuckLogId}) completed in {$phaseElapsed}s", 'geslib');
        }

        // Phase 4: Process files
        $phaseStart = microtime(true);
        while( $geslibApiDbLogManager->checkLoggedStatus() ) {
            $log_id = $geslibApiDbLogManager->getGeslibLoggedId();
            if ( !$log_id ) {
                break;
            }
            $filesProcessed++;
            $geslibApiDbLogManager->setLogStatus( $log_id, 'queued' );
            $geslibApiLines->storeToLines($log_id);
            foreach( $queuetypes as $queuetype ) {
                $geslibApiDbQueueManager->processFromQueue( $queuetype );
            }
            $geslibApiDbLinesManager->truncateGeslibLines();
            $geslibApiDbLogManager->setLogStatus( $log_id, 'processed');
        }
        $phaseElapsed = round(microtime(true) - $phaseStart, 1);
        $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Phase PROCESS FILES completed in {$phaseElapsed}s", 'geslib');

        // Run summary
        $totalElapsed = round(microtime(true) - $cronStart, 1);
        $memPeak = round(memory_get_peak_usage(true) / 1048576, 1);
        $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
            "=== CRON SUMMARY === Duration: {$totalElapsed}s | Files: {$filesProcessed} | Memory peak: {$memPeak}MB ===", 'geslib');
    }
    /**
     * covers_cron_function
     *
     * @return void
     */
    function covers_cron_function() {

    }
}