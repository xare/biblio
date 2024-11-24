<?php

namespace Inc\Geslib\Api;

use Inc\Biblio\Api\BiblioApi;

class GeslibApiStoreData {
    private $db;
    private $biblioApi;

    public function __construct() {
        $this->db = new GeslibApiDbManager();
        $this->biblioApi = new BiblioApi;
    }

    public function storeProductCategories() {
        global $wpdb;
        $queueTable = $wpdb->prefix . 'geslib_queues';
        $geslibApiDbQueueManager = new GeslibApiDbQueueManager;
        $geslibApiDbLinesManager = new GeslibApiDbLinesManager;
        $product_categories = $geslibApiDbLinesManager->getCategoriesFromGeslibLines();
        $batch_size = 3000; // Choose a reasonable batch size
		$batch = [];
        foreach($product_categories as $product_category) {
            $item = [
                'log_id' => $product_category['log_id'],
                'geslib_id' => $product_category['geslib_id'],
                'type' => 'store_categories'  // type to identify the task in processQueue
            ];
            if( isset($product_category['content']['action'])) {
                $item['action'] = $product_category['content']['action'];
            }
            $item['data'] = $product_category['content'];
            $batch[] = $item;
            if ( count( $batch ) >= $batch_size ) {
                $geslibApiDbQueueManager->insertCategoriesIntoQueue( $batch );
                $batch = [];
            }
        }
        // Return a status message indicating success or failure and/or count of categories added.
        $totalAdded = count($product_categories);
        $this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Added {$totalAdded} product categories to the queue.", 'geslib' );
    }


    /**
     * storeEditorials
     *
     * @return void
     */
    public function storeEditorials() {
        $geslibApiDbManager = new GeslibApiDbManager;
        $geslibApiDbQueueManager = new GeslibApiDbQueueManager;
        $geslibApiDbLinesManager = new GeslibApiDbLinesManager;
        $editorials = $geslibApiDbLinesManager->getEditorialsFromGeslibLines();
        $batch_size = 3000; // Choose a reasonable batch size
		$batch = [];
        foreach( $editorials as $editorial ) {
            //$geslibApiDbManager->storeEditorials( $editorial );
            $item = [
                'log_id' => $editorial['log_id'],
                'geslib_id' => $editorial['geslib_id'],
                'type' => 'store_editorials'  // type to identify the task in processQueue
            ];
            if( isset( $editorial['content']['action'] ) ) {
                $item['action'] = $editorial['content']['action'];
            }
            $item['data'] = $editorial['content'];
            $batch[] = $item;
            if ( count( $batch ) >= $batch_size ) {
                $geslibApiDbQueueManager->insertEditorialsIntoQueue( $batch );
                $batch = [];
            }
        }
    }

    /**
     * storeAuthors
     *
     * Store Authors into geslib_queue('autors')
     *
     * @return void
     */
    public function storeAuthors() {
        $geslibApiDbLinesManager = new GeslibApiDbLinesManager;
        $geslibApiDbQueueManager = new GeslibApiDbQueueManager;
        $geslibApiDbManager = new GeslibApiDbManager;
        $authors = $geslibApiDbLinesManager->getAuthorsFromGeslibLines();
        $batch_size = 3000; // Choose a reasonable batch size
		$batch = [];
        foreach( $authors as $author ) {
            $item = [
                'log_id' => $author['log_id'],
                'geslib_id' => $author['geslib_id'],
                'type' => 'store_autors'  // type to identify the task in processQueue
            ];
            if( isset( $author['content']['action'] ) ) {
                $item['action'] = $author['content']['action'];
            }
            $item['data'] = $author['content'];
            $batch[] = $item;
            if ( count( $batch ) >= $batch_size ) {
                $geslibApiDbQueueManager->insertAuthorsIntoQueue( $batch );
                $batch = [];
            }
        }
        if(count($batch) > 0) {
            $geslibApiDbQueueManager->insertAuthorsIntoQueue( $batch );
        }
    }
}