<?php

namespace Inc\Geslib\Api;

use Inc\Biblio\Api\BiblioApi;

class GeslibApiStoreData {
    private $db;
    private $biblioApi;

    /**
     * Constructor for the GeslibApiStoreData class.
     *
     * Initializes any required properties or dependencies for the class.
     */
    public function __construct() {
        $this->db = new GeslibApiDbManager();
        $this->biblioApi = new BiblioApi;
    }

    /**
     * Stores product categories retrieved from the Geslib API.
     *
     * This method handles the process of fetching product categories data from the Geslib API
     * and saving it into the local database or storage system. It may include data validation,
     * transformation, and error handling as required by the application.
     *
     * @return bool  True when every batch was enqueued; false on the first insert failure.
     */
    public function storeEditorials(): bool {
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
                if ( ! $geslibApiDbQueueManager->insertEditorialsIntoQueue( $batch ) ) {
                    return false;
                }
                $batch = [];
            }
        }
        if ( ! empty( $batch ) ) {
            if ( ! $geslibApiDbQueueManager->insertEditorialsIntoQueue( $batch ) ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Stores author data retrieved from the Geslib API.
     *
     * This method handles the process of fetching author information from the Geslib API
     * and saving it into the local database or storage system.
     * 
     * Store Authors into geslib_queue('autors')
     *
     * @return bool  True when every batch was enqueued; false on the first insert failure.
     */
    public function storeAuthors(): bool {
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
                if ( ! $geslibApiDbQueueManager->insertAuthorsIntoQueue( $batch ) ) {
                    return false;
                }
                $batch = [];
            }
        }
        if(count($batch) > 0) {
            if ( ! $geslibApiDbQueueManager->insertAuthorsIntoQueue( $batch ) ) {
                return false;
            }
        }
        return true;
    }
}