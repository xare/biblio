<?php

/**
 * @package Covers
 */
namespace Inc\Covers\Base;

use Inc\Biblio\Api\BiblioApi;
use Inc\Covers\Api\CoversApi;
use Inc\Covers\Api\CoversApiDbManager;
use Inc\Covers\Api\CoversApiDbLogManager;


class Cron extends BaseController {
    public function register() {

        if ( ! wp_next_scheduled( 'covers_cron_event' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'covers_cron_event' );
        }
        add_action( 'covers_cron_event', [ $this, 'coversCron' ] );

        // Register a custom cron schedule called missing covers

        if ( ! wp_next_scheduled( 'covers_missing_cron_event' ) ) {
            wp_schedule_event( time(), 'twicedaily', 'covers_missing_cron_event' );
        }
        add_action( 'covers_missing_cron_event', [ $this, 'missingCoversCron' ] );
    }

    /**
     * custom_cron_schedule
     *
     * @param  array $schedules
     * @return array
     */
    /* function custom_cron_schedule( array $schedules ): array {
        $schedules['every_fifteen_minutes'] = [
            'interval' => 15 * 60, // 15 minutes in seconds
            'display'  => __( 'Every 15 Minutes' ),
        ];
        return (array) $schedules;
    } */

    /**
     * coversCron
     *
     * Geslib-style run structure: start marker, per-phase markers with
     * elapsed time, one compact line per batch, final summary with totals
     * aggregated from both scanProducts passes (dilve + cegal).
     *
     * @return void
     */
    function coversCron() {
        $batch_size = 100;
        $coversApi = new CoversApi;
        $coversApiDbLogManager = new CoversApiDbLogManager;
        $biblioApi = new BiblioApi;
        $title = __CLASS__ . ':' . __LINE__ . ' ' . __FUNCTION__;
        
        $log_id_raw = $coversApiDbLogManager->insertLogData('logged');
        $log_id = (int) $log_id_raw;
        if ( false === $log_id_raw ) {
            $biblioApi->getLogger()->error($title, 'covers_cron: could not create covers_log row', 'covers');
        }
        $cron_start = microtime(true);
        $biblioApi->getLogger()->info($title, '=== COVERS CRON START === log_id=' . $log_id, 'covers');

        $totals = [
            'scanned' => 0,
            'attached' => 0,
            'invalid_ean' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        $biblioApi->getLogger()->info($title, '--- Phase: DILVE ---', 'covers');
        $phase_start = microtime(true);
        do {
            $offset = (int) get_option( 'last_processed_offset', 0 );
            $stats = [];
            $response = (string) $coversApi->scanProducts($log_id, $batch_size, $offset, 'dilve', $stats);
            $response = (array) json_decode($response, true);
            update_option( 'last_processed_offset', $offset + $batch_size );
            if( $response['hasMore'] == false ) {
                update_option( 'last_processed_offset', 0 );
            }
            foreach ( $totals as $key => $value ) {
                $totals[$key] = $value + ( $stats[$key] ?? 0 );
            }
            $biblioApi->getLogger()->info($title, 'batch phase=dilve offset=' . $offset . ' scanned=' . ( $stats['scanned'] ?? 0 ), 'covers');
        } while( $response['hasMore'] == true );
        $biblioApi->getLogger()->info($title, '--- Phase: DILVE completed in ' . round(microtime(true) - $phase_start, 1) . 's ---', 'covers');
        $biblioApi->getLogger()->info($title, '--- Phase: CEGAL ---', 'covers');
        $phase_start = microtime(true);
        do {
            $offset = (int) get_option( 'last_processed_offset', 0 );
            $stats = [];
            $response = (string) $coversApi->scanProducts($log_id, $batch_size, $offset, 'cegal' , $stats);
            $response = (array) json_decode($response, true);
            update_option( 'last_processed_offset', $offset + $batch_size );
            if( $response['hasMore'] == false ) {
                update_option( 'last_processed_offset', 0 );
            }
            foreach ( $totals as $key => $value ) {
                $totals[$key] = $value + ( $stats[$key] ?? 0 );
            }
            $biblioApi->getLogger()->info($title, 'batch phase=cegal offset=' . $offset . ' scanned=' . ( $stats['scanned'] ?? 0 ), 'covers');
        } while( $response['hasMore'] == true );
        $biblioApi->getLogger()->info($title, '--- Phase: CEGAL completed in ' . round(microtime(true) - $phase_start, 1) . 's ---', 'covers');

        $total_elapsed = round(microtime(true) - $cron_start, 1);
        $mem_peak = round(memory_get_peak_usage(true) / 1048576, 1);
        $biblioApi->getLogger()->info($title,
            '=== COVERS CRON SUMMARY === Duration: ' . $total_elapsed . 's'
            . ' | Scanned: ' . $totals['scanned']
            . ' | Attached: ' . $totals['attached']
            . ' | Invalid EAN: ' . $totals['invalid_ean']
            . ' | Failed: ' . $totals['failed']
            . ' | Skipped: ' . $totals['skipped']
            . ' | Memory peak: ' . $mem_peak . ' MB ===',
            'covers');
    }

    /**
     * missingCoversCron
     *
     * @return void
     */
    public function missingCoversCron() {
        $coversApi = new CoversApi;
        $biblioApi = new BiblioApi;
        $title = __CLASS__ . ':' . __LINE__ . ' ' . __FUNCTION__;
        $cron_start = microtime(true);
        $biblioApi->getLogger()->info($title, '=== MISSING COVERS CRON START ===', 'missingcovers');
        $stats = [];
        $coversApi->attach_local_missing_covers($stats);
        $total_elapsed = round(microtime(true) - $cron_start, 1);
        $mem_peak = round(memory_get_peak_usage(true) / 1048576, 1);
        $biblioApi->getLogger()->info($title,
            '=== MISSING COVERS SUMMARY === Duration: ' . $total_elapsed . 's'
            . ' | Candidates: ' . ( $stats['candidates'] ?? 0 )
            . ' | Found: ' . ( $stats['found'] ?? 0 )
            . ' | Attached: ' . ( $stats['attached'] ?? 0 )
            . ' | Already: ' . ( $stats['already'] ?? 0 )
            . ' | Missing: ' . ( $stats['missing'] ?? 0 )
            . ' | Failed: ' . ( $stats['failed'] ?? 0 )
            . ' | Memory peak: ' . $mem_peak . ' MB ===',
            'missingcovers');
    }

}