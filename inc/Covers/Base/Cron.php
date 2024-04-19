<?php

/**
 * @package Covers
 */
namespace Inc\Covers\Base;

use Inc\Covers\Api\CoversApi;
use Inc\Covers\Api\CoversApiDbLogManager;
use Inc\Covers\Api\CoversApiDbManager;
use Inc\Covers\Api\CoverslApiDbLogManager;
use Inc\Covers\Api\CoverslApiDbManager;

class Cron extends BaseController {
    public function register() {

        add_filter( 'cron_schedules', [ $this,'custom_cron_schedule'] );
        if ( ! wp_next_scheduled( 'covers_cron_event' ) ) {
            wp_schedule_event( time(), 'hourly', 'covers_cron_event' );
        }
        add_action( 'covers_cron_event', [ $this, 'coversCron' ] );
    }

    /**
     * custom_cron_schedule
     *
     * @param  array $schedules
     * @return array
     */
    function custom_cron_schedule( array $schedules ): array {
        $schedules['every_fifteen_minutes'] = [
            'interval' => 15 * 60, // 15 minutes in seconds
            'display'  => __( 'Every 15 Minutes' ),
        ];
        return (array) $schedules;
    }

    /**
     * cegalCron
     *
     * @return void
     */
    function cegalCron() {
        $batch_size = 38;
        $coversApi = new CoversApi;
        $coversApiDbManager = new CoversApiDbManager;
        $coversApiDbLogManager = new CoversApiDbLogManager;
        error_log('Start Cron '. date('Y-m-d') );
        $totalLines = $coversApiDbManager->countAllProducts();
        $log_id = $coversApiDbLogManager->insertLogData('logged', $totalLines);
        do {
            $offset = get_option( 'last_processed_offset', 0 );
            $jsonResponse = $coversApi->scanProducts($log_id, $batch_size, $offset );
            $responseArray = json_decode($jsonResponse, true);
            error_log(var_export($responseArray, true));
            error_log(var_export($responseArray['hasMore'], true));
            update_option( 'last_processed_offset', $offset + $batch_size );
            if( $responseArray['hasMore'] == false ) {
                update_option( 'last_processed_offset', 0 );
            }
        } while( $responseArray['hasMore'] == true );
        error_log('End Cron '. $jsonResponse );
    }

}