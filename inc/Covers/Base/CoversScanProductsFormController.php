<?php
/**
 * @package covers
 */
namespace Inc\Covers\Base;

use Inc\Covers\Api\CoversApi;
use Inc\Covers\Api\CoversApiDbLogManager;
use Inc\Covers\Api\CoversApiDbManager;
use Inc\Biblio\Base\BaseController;

/**
 * @class CoversScanProductsFormController
 */
class CoversScanProductsFormController extends BaseController
{
    public $adminNotice = '';
    /**
     * register
     *
     * @return void
     */
    public function register()
    {
        //add_action('admin_init', [$this, 'handleFormSubmission']);
        $actions = [
            'hello_world',
            'scan_products',
            'scan_subtitles',
        ];
        //add_action('wp_ajax_covers_log_queue', [$this, 'ajaxHandleCoversLogQueue']);
        foreach ( $actions as $action ) {
            $camelCase = str_replace( ' ', '', ucwords( str_replace( '_', ' ', $action ) ) );
            add_action( 'wp_ajax_covers_' . $action, [ $this, 'ajaxHandle' . $camelCase ] );
        }

        add_action('admin_notices', [ $this, 'displayAdminNotice' ]);
    }

    /**
     * ajaxHandleHelloWorld
     *
     * @return void
     */
    public function ajaxHandleHelloWorld() {
        check_ajax_referer('covers_scan_products_form', 'covers_nonce');
        update_option('covers_admin_notice', 'Hello world!');
        wp_send_json_success(['message' => 'Hello world!']);
    }

    /**
     * ajaxHandleScanProducts
     *
     * @return void
     */
    public function ajaxHandleScanProducts() {
        check_ajax_referer('covers_scan_products_form', 'covers_nonce');
        update_option('covers_admin_notice', 'File Checked!');
        $coversApi = new CoversApi;
        $coversApiDbManager = new CoversApiDbManager;
        $coversApiDbLogManager = new CoversApiDbLogManager;
        $batch_size = 10;
        $totalLines = $coversApiDbManager->countAllProducts();
        $log_id = $coversApiDbLogManager->insertLogData('logged', $totalLines);
        $response = $coversApi->scanProducts($log_id, 0, -1, 'dilve');
        $response = $coversApi->scanProducts($log_id, 0, -1, 'cegal');
        wp_send_json_success($response);
    }

    /**
     * ajaxHandleScanSubtitles
     *
     * Dilve subtitle scan (fill-empty-only, metadata only). Action and nonce
     * are registered/checked as a matched pair: wp_ajax_covers_scan_subtitles
     * + covers_nonce (action family covers_scan_products_form). Small batch;
     * returns the scanSubtitles() stats as JSON.
     *
     * @return void
     */
    public function ajaxHandleScanSubtitles() {
        check_ajax_referer('covers_scan_products_form', 'covers_nonce');
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Forbidden' ], 403 );
        }
        $coversApi = new CoversApi;
        $batch_size = 10;
        $stats = [];
        $coversApi->scanSubtitles($batch_size, 0, $stats);
        wp_send_json_success($stats);
    }

    public function displayAdminNotice() {
        if ($this->adminNotice !== '') {
            echo '<div class="notice notice-success is-dismissible">';
                echo '<p>' . $this->adminNotice . '</p>';
            echo '</div>';
        }
    }
}
