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
     * cegalCron
     *
     * @return void
     */
    function coversCron() {
        $batch_size = 100;
        $coversApi = new CoversApi;
        $coversApiDbLogManager = new CoversApiDbLogManager;
        $biblioApi = new BiblioApi;
        

        $log_id = (int) $coversApiDbLogManager->insertLogData('logged');
        $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Start Cron Log ID:' . $log_id, 'covers');
        do {
            $offset = (int) get_option( 'last_processed_offset', 0 );
            $response = (string) $coversApi->scanProducts($log_id, $batch_size, $offset );
            $response = (array) json_decode($response, true);
            update_option( 'last_processed_offset', $offset + $batch_size );
            if( $response['hasMore'] == false ) {
                update_option( 'last_processed_offset', 0 );
            }
        } while( $response['hasMore'] == true );
        $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'End Dilve Cron Log ID:' . $log_id, 'covers');
        do {
            $offset = (int) get_option( 'last_processed_offset', 0 );
            $response = (string) $coversApi->scanProducts($log_id, $batch_size, $offset, 'cegal' );
            $response = (array) json_decode($response, true);
            update_option( 'last_processed_offset', $offset + $batch_size );
            if( $response['hasMore'] == false ) {
                update_option( 'last_processed_offset', 0 );
            }
        } while( $response['hasMore'] == true );
        $biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'End Cegal Cron Log ID:' . $log_id, 'covers');
    }

}