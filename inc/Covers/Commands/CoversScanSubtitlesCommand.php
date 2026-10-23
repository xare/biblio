<?php

namespace Inc\Covers\Commands;

use Inc\Covers\Api\CoversApi;
use WP_CLI;

/**
 * Class CoversScanSubtitlesCommand
 */
class CoversScanSubtitlesCommand {

    private $coversApi;
    public function __construct() {
        $this->coversApi = new CoversApi();
    }
    public function register() {
        if ( class_exists( 'WP_CLI' ) ) {
            WP_CLI::add_command( 'covers scanSubtitles', [$this, 'execute'] );
        }
    }
    /**
     * Scan Dilve ONIX metadata for subtitles of published products without one.
     *
     * Fill-empty-only: products whose _subtitle meta is already set are never
     * candidates. No cover download and no covers_log rows are involved.
     *
     * ## OPTIONS
     *
     * [--batch_size=<n>]
     * : Number of products to process in this run. Default 100.
     *
     * [--offset=<n>]
     * : SQL offset into the candidate list. Default 0.
     *
     * ## EXAMPLES
     *
     *     wp covers scanSubtitles --batch_size=10
     *
     * @when after_wp_load
     */
    public function execute( $args, $assoc_args ) {
        $batch_size = isset( $assoc_args['batch_size'] ) ? (int) $assoc_args['batch_size'] : 100;
        $offset = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
        $stats = [];
        $response = $this->coversApi->scanSubtitles( $batch_size, $offset, $stats );
        if ( false === $response ) {
            WP_CLI::success( 'No products without subtitle.' );
            return;
        }
        WP_CLI::line( 'Scanned: ' . $stats['scanned'] );
        WP_CLI::line( 'Filled: ' . $stats['filled'] );
        WP_CLI::line( 'Skipped (already has subtitle): ' . $stats['skipped_has_subtitle'] );
        WP_CLI::line( 'Skipped (no subtitle in ONIX): ' . $stats['skipped_no_subtitle_in_onix'] );
        WP_CLI::line( 'Invalid EAN: ' . $stats['invalid_ean'] );
        WP_CLI::line( 'Failed: ' . $stats['failed'] );
        WP_CLI::success( 'Subtitle scan complete.' );
    }
}
