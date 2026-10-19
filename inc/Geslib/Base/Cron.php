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
        $this->ensure_twohourly( 'geslib_cron_event' );
        $this->ensure_twohourly( 'geslib_removeUncategorized_cron_event' );

        add_action( 'geslib_cron_event', [ $this, 'geslib_cron_function' ] );
        add_action( 'geslib_removeUncategorized_cron_event', [ $this, 'geslib_remove_uncategorized_cron_function' ] );
    }

    /**
     * Guarantee a hook runs on the twohourly schedule, migrating away from any
     * interval an earlier release left behind.
     *
     * The migration is necessary because a stale event still counts as
     * "scheduled": a plain wp_next_scheduled() guard skips it and keeps the old
     * interval forever (geslib_cron_event sat at twicedaily/12h in production
     * exactly this way). Everything here is conditional on the stored interval -
     * register() runs on every admin request, so an unconditional
     * clear-and-reschedule would push the next run out indefinitely.
     *
     * @param string $hook Cron hook name.
     * @return void
     */
    private function ensure_twohourly( string $hook ): void {
        $event = wp_get_scheduled_event( $hook );

        if ( $event && 'twohourly' === $event->schedule ) {
            return;
        }

        if ( $event ) {
            // WP stores cron entries per timestamp and does not dedupe, so a
            // failed removal followed by a fresh schedule would leave two events
            // behind and make the hook fire twice. Leaving the stale one alone
            // is the safer failure mode.
            if ( ! wp_unschedule_event( $event->timestamp, $hook ) ) {
                ( new BiblioApi() )->getLogger()->error(
                    'ERROR ' . __CLASS__ . ':' . __LINE__ . ' ' . __FUNCTION__,
                    "Could not unschedule {$hook} on '{$event->schedule}' - leaving it unchanged",
                    'geslib'
                );
                return;
            }
            ( new BiblioApi() )->getLogger()->info(
                'INFO ' . __CLASS__ . ':' . __LINE__ . ' ' . __FUNCTION__,
                "Migrated {$hook} from '{$event->schedule}' to twohourly",
                'geslib'
            );
        }

        $this->schedule_twohourly( $hook );
    }

    /**
     * Schedule a twohourly event, logging the WP_Error on failure instead of
     * silently dropping it the way a bare wp_schedule_event() call does.
     *
     * @param string $hook Cron hook name.
     * @return void
     */
    private function schedule_twohourly( string $hook ): void {
        $result = wp_schedule_event( time(), 'twohourly', $hook, [], true );
        if ( is_wp_error( $result ) ) {
            ( new BiblioApi() )->getLogger()->error(
                'ERROR ' . __CLASS__ . ':' . __LINE__ . ' ' . __FUNCTION__,
                "Failed to schedule {$hook}: " . $result->get_error_message(),
                'geslib'
            );
        }
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
