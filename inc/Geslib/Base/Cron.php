<?php

/**
 * @package geslib
 *
 * Cron architecture: time-limited loop processing.
 * Each cron trigger processes files in a loop until the time limit is reached.
 * No self-scheduling events — the main cron does everything.
 *
 * Each file is processed ATOMICALLY:
 *   storeToLines → processQueues → truncateLines → markProcessed
 *
 * The loop handles both new files (status='logged') and stuck files (status='queued').
 * If a file was partially processed in a previous run, existing queue items are
 * preserved and processing resumes without re-parsing the file.
 *
 * The loop stops when approaching the web server timeout (~25s used,
 * leaving 5s safety margin for a 30s timeout). The next cron trigger
 * (every 2h) continues where it left off.
 */
namespace Inc\Geslib\Base;

use Inc\Biblio\Api\BiblioApi;
use Inc\Biblio\Base\CustomTaxonomyController;
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

    /** All queue types in processing order */
    const ALL_QUEUES = [
        'store_lines',
        'build_content',
        'store_products',
        'store_autors',
        'store_categories',
        'store_editorials',
        'store_colecciones',
    ];

    /** Time limit in seconds — allows processing large files within one request */
    const TIME_LIMIT = 16000;

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

    /** Heartbeat staleness threshold in seconds — if no heartbeat in this window, lock is considered dead */
    const LOCK_STALE_SECONDS = 180; // 3 minutes (batches take ~2min, so 3min gives margin)

    /**
     * Main cron: reads files, then processes them in a time-limited loop.
     */
    function geslib_cron_function() {
        // Heartbeat-based lock: store a timestamp, update after each batch.
        // If the process dies (SIGKILL/SIGTERM from server timeout), the lock
        // goes stale after LOCK_STALE_SECONDS and the next cron can proceed.
        $lock_key = 'geslib_cron_lock';
        $lock = get_transient( $lock_key );
        if ( $lock && ( time() - (int) $lock ) < self::LOCK_STALE_SECONDS ) {
            $biblioApi = new BiblioApi;
            $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
                '=== CRON SKIPPED — another run is still active ===', 'geslib');
            return;
        }
        // Set heartbeat (TTL is just a hard fallback; heartbeat decides staleness)
        set_transient( $lock_key, time(), 3600 );

        // Also try to release on graceful shutdown (fatal errors, normal exit)
        // Does NOT fire on SIGKILL/SIGTERM — heartbeat staleness covers that
        register_shutdown_function( function() use ( $lock_key ) {
            delete_transient( $lock_key );
        } );

        // Keep processing even if the HTTP connection is dropped by the server
        ignore_user_abort(true);
        set_time_limit(0);

        $geslibApiReadFiles = new GeslibApiReadFiles();
        $geslibApiDbLogManager = new GeslibApiDbLogManager;
        $geslibApiLines = new GeslibApiLines;
        $geslibApiDbLinesManager = new GeslibApiDbLinesManager;
        $geslibApiDbQueueManager = new GeslibApiDbQueueManager;
        $biblioApi = new BiblioApi;

        $cronStart = microtime(true);
        $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, '=== CRON START ===', 'geslib');

        // Phase 1: Read files (fast)
        $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, '--- Phase: READ FILES ---', 'geslib');
        $phaseStart = microtime(true);
        $geslibApiReadFiles->readFolder();
        $phaseElapsed = round(microtime(true) - $phaseStart, 1);
        $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Phase READ FILES completed in {$phaseElapsed}s", 'geslib');

        // Ensure custom taxonomies
        $customTaxonomyController = new CustomTaxonomyController();
        $customTaxonomyController->ensureTaxonomiesLoaded();

        // Phase 2: Process files in time-limited loop
        $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
            '--- Phase: PROCESS FILES ---', 'geslib');
        $filesProcessed = 0;
        $totalProducts = 0;

        while ( $geslibApiDbLogManager->checkLoggedStatus() ) {
            $log_id = $geslibApiDbLogManager->getGeslibLoggedId();
            if ( ! $log_id ) {
                break;
            }

            $fileStart = microtime(true);

            // Step 1: Mark as queued
            $geslibApiDbLogManager->setLogStatus( $log_id, 'queued' );

            // Step 2: Read file and create queue items (skip if already queued from previous run)
            if ( ! $geslibApiDbQueueManager->hasQueueItemsForLog( $log_id ) ) {
                $geslibApiLines->storeToLines($log_id);
            } else {
                $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
                    "Log {$log_id}: resuming from existing queue items (skip re-parse)", 'geslib');
            }

            // Step 3: Process ALL queues atomically
            foreach ( self::ALL_QUEUES as $queuetype ) {
                $remaining = $geslibApiDbQueueManager->countGeslibQueue( $queuetype );
                if ($remaining > 0) {
                    $geslibApiDbQueueManager->processFromQueue( $queuetype );
                    // Heartbeat: refresh lock after each queue type completes
                    set_transient( $lock_key, time(), 3600 );
                }
            }

            // Step 4: Truncate lines
            $geslibApiDbLinesManager->truncateGeslibLines();

            // Step 5: Mark as processed — ONLY after everything is done
            $geslibApiDbLogManager->setLogStatus( $log_id, 'processed');
            // Move to processed/ AFTER status is set — file has been fully handled
            $processedFilename = $geslibApiDbLogManager->getGeslibLoggedFilename( $log_id );
            if ( $processedFilename ) {
                $geslibApiReadFiles->moveToProcessed( $processedFilename );
            }

            $fileElapsed = round(microtime(true) - $fileStart, 1);
            $filesProcessed++;
            $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
                "FILE (log {$log_id}) completed in {$fileElapsed}s [{$filesProcessed} files total]", 'geslib');

            // Check time limit AFTER completing current file (preserves atomicity)
            $elapsed = microtime(true) - $cronStart;
            if ( $elapsed >= self::TIME_LIMIT ) {
                $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
                    "Time limit reached ({$elapsed}s) — stopping. {$filesProcessed} files processed.", 'geslib');
                break;
            }
        }

        // Run summary
        $totalElapsed = round(microtime(true) - $cronStart, 1);
        $memPeak = round(memory_get_peak_usage(true) / 1048576, 1);
        $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
            "=== CRON SUMMARY === Duration: {$totalElapsed}s | Files: {$filesProcessed} | Memory peak: {$memPeak}MB ===", 'geslib');

        // Release lock so next cron trigger can run
        delete_transient( $lock_key );
    }

    function geslib_remove_uncategorized_cron_function() {
        $geslibApiDbTaxonomyManager = new GeslibApiDbTaxonomyManager;
        $biblioApi = new BiblioApi();
        $biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Start process', 'geslib');
        $geslibApiDbTaxonomyManager->removeUncategorizedCategory();
    }
    
}
