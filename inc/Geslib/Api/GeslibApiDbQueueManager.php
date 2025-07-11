<?php

namespace Inc\Geslib\Api;

use Inc\Biblio\Api\BiblioApi;

class GeslibApiDbQueueManager extends GeslibApiDbManager {

	private $biblioApi;

	public function __construct() {
		$this->biblioApi = new BiblioApi;
	}

    /**
     * insertLinesIntoQueue
	 * Inserts each line from INTER*** to the store_lines queue
	 * Called by GeslibApiLines
     *
     * @param  array $batch
     * @return void
     */
    public function insertLinesIntoQueue( array $batch ): void {
		global $wpdb;
		foreach ($batch as $item) {
			try {
				$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Insertando linea: ".$item['data'] , 'geslib');
				$wpdb->insert($wpdb->prefix . self::GESLIB_QUEUES_TABLE, $item, ['%d', '%d', '%s', '%s', '%s', '%s']);
			} catch( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage() , 'geslib');
				continue;
            }
		}
	}
    /**
     * insertProductsIntoQueue
     *
     * @param  array $batch
     * @return void
     */
    public function insertProductsIntoQueue( array $batch ) {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		foreach ( $batch as $item ) {
			try {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Inserting line: ". $item['data'], 'geslib' );
				$insertedid = $wpdb->insert( $queues_table, $item, ['%d', '%d', '%s', '%s', '%s', '%s'] );
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Inserted id: ". $insertedid, 'geslib' );
				try {
					$wpdb->delete(
						$queues_table,
						[
							'geslib_id' => $item['geslib_id'],
							'log_id' => $item['log_id'],
							'entity' => 'product',
							'type' => 'build_content'
						],['%d','%d','%s','%s']
					);
				} catch( \Exception $exception ) {
					$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
					continue;
				}
			} catch( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
				continue;
			}
		}
	}

	/**
	 * insertAuthorsIntoQueue
	 *
	 * @param  array $batch
	 * @return bool
	 */
	public function insertAuthorsIntoQueue( array $batch ): void {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		foreach ( $batch as $item ) {
			try{
				$wpdb->insert( $queues_table, $item, ['%d', '%d', '%s', '%s', '%s', '%s'] );
				try {
					$wpdb->delete(
						$queues_table,
						[
							'geslib_id' => $item['geslib_id'],
							'log_id' => $item['log_id'],
							'entity' => 'autors',
							'type' => 'store_autors'
						],['%d','%d','%s','%s']
					);
				} catch( \Exception $exception ) {
					$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
					continue;
				}
			} catch( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib');
				continue;
			}
		}
	}

	public function insertEditorialsIntoQueue( array $batch ) {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		foreach ( $batch as $item ) {
			try{
				$wpdb->insert( $queues_table, $item, ['%d', '%d', '%s', '%s', '%s', '%s'] );
				try {
					$wpdb->delete(
						$queues_table,
						[
							'geslib_id' => $item['geslib_id'],
							'log_id' => $item['log_id'],
							'entity' => 'editorial',
							'type' => 'store_editorials'
						],['%d','%d','%s','%s']
					);
				} catch( \Exception $exception ) {
					$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
					continue;
				}
			} catch( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
				continue;
			}
		}
	}

	public function insertColeccionesIntoQueue( array $batch ) {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		foreach ( $batch as $item ) {
			try{
				$wpdb->insert( $queues_table, $item, ['%d', '%d', '%s', '%s', '%s', '%s'] );
				try {
					$wpdb->delete(
						$queues_table,
						[
							'geslib_id' => $item['geslib_id'],
							'log_id' => $item['log_id'],
							'entity' => 'coleccion',
							'type' => 'store_colecciones'
						], [ '%d', '%d', '%s', '%s' ]
					);
				} catch( \Exception $exception ) {
					$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib');
					continue;
				}
			} catch( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
				continue;
			}
		}
	}

	/**
	 * insertCategoriesIntoQueue
	 *
	 * @param  mixed $batch
	 * @return void
	 */
	public function insertCategoriesIntoQueue( array $batch ) {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		foreach ( $batch as $item ) {
			try{
				$wpdb->insert( $queues_table, $item, ['%d', '%d', '%s', '%s', '%s', '%s'] );
				try {
					$wpdb->delete(
						$queues_table,
						[
							'geslib_id' => $item['geslib_id'],
							'log_id' => $item['log_id'],
							'entity' => 'product_cat',
							'type' => 'store_categories'
						],['%d','%d','%s','%s']
					);
				} catch( \Exception $exception ) {
					$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
					continue;
				}
			} catch( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
				continue;
			}
		}
	}

    /**
	 * deleteItemFromQueue
	 *
	 * @param  string $type
	 * @param  int $log_id
	 * @param  int $geslib_id
	 * @return bool
	 */
	public function deleteItemFromQueue( string $type, int $log_id, int $geslib_id ): bool {
		global $wpdb;
		$geslib_id = ( $geslib_id == null )? 0 : $geslib_id;
		try {
			$wpdb->delete(
				$wpdb->prefix . self::GESLIB_QUEUES_TABLE,
				[
					'type' => $type,
					'geslib_id' => $geslib_id,
					'log_id' => $log_id
				],
				[
					'%s', // placeholder for 'type'
					'%d', // placeholder for 'geslib_id', assuming it's an integer
					'%d'  // placeholder for 'log_id', assuming it's an integer
				]
			);
			return true;
		} catch(\Exception $exception) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
			return false;
		}
	}

	 /**
     * deleteItemsFromQueue
     *
     * @param  string $type
     * @return bool
     */
    public function deleteItemsFromQueue( string $type ): bool {
		global $wpdb;
        try {
            // Delete query using Drupal's Database API
			$wpdb->delete(
				$wpdb->prefix . self::GESLIB_QUEUES_TABLE,
				[
					'type' => $type,
				],
				[
					'%s', // placeholder for 'type'
				]
			);
            return true;
        } catch (\Exception $exception) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
            return false;
        }
    }

	/**
	 * processFromQueue
	 *
	 * @param  mixed $type
	 * @return bool
	 */
	public function processFromQueue( string $type ): bool {
		
		global $wpdb;
        $table_name = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		$preparedQuery1 = $wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE `type` = %s", $type);
		$queue_count1 = $wpdb->get_var($preparedQuery1);
		if($queue_count1 == 0) {
			return false;
		}
		
		// Define a mapping of types to their respective processing functions
		// Type
		/*
			'build_content' => 'processBatchBuildContent',
			'store_lines' => 'processBatchStoreLines',
			'store_products' => 'processBatchStoreProducts',
			'store_autors' => 'processBatchStoreAutors',
			'store_editorials' => 'processBatchStoreEditorials',
			'store_categories' => 'processBatchStoreCategories',
		*/
		$methodName = 'processBatch' . str_replace('_', '', ucwords($type, '_'));
		echo $methodName;
		// Check if the provided type is valid
		if (method_exists($this, $methodName)) {
			$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Running: ' . $methodName);
			do {
				// Call the corresponding processing function based on the type
				$this->$methodName(3000);

				// Get the count of remaining items in the queue safely
				$preparedQuery = $wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE `type` = %s", $type);
				$queue_count = $wpdb->get_var($preparedQuery);

			} while ($queue_count > 0);
			return true;
		} else {
			// Optionally handle the case where the type is not recognized
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Unrecognized queue type: ' . $type);
			return false;
		}
	}

	public function processBatchBuildContent( int $batchSize = 100 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		$queue = $this->getBatchFromQueue( $batchSize, 'build_content' );

		foreach ( $queue as $task ) {
			$type = match( $task->entity ) {
				'product' => 'store_products',
				'autors' => 'store_autors',
				'editorial' => 'store_editorials',
				'coleccion' => 'store_colecciones',
				'product_cat' => 'store_categories',
			};
			try {
				$wpdb->update( $table_name,
			 				['type' => $type],
							[
								'geslib_id' => $task->geslib_id,
								'entity' => $task->entity],
							'%s',
							['%d','%s'] );
			} catch (\Exception $exception) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib' );
				continue;
			}
		}
	}
	/**
	 * processBatchStoreLines
	 *
	 * @param  int $batchSize
	 * @return void
	 */
	public function processBatchStoreLines( int $batchSize = 100 ) {
        $queue = $this->getBatchFromQueue( (int) $batchSize, 'store_lines' );
        // If there are no tasks, exit the function.
        $geslibApiLines = new GeslibApiLines();
        foreach ($queue as $task) {
            $geslibApiLines->readLine( $task->data, (int) $task->log_id );
        }
    }

	/**
	 * processBatchStoreProducts
	 *
	 * @param  int $batchSize
	 * @return void
	 */
	public function processBatchStoreProducts( int $batchSize = 100 ) {
		$geslibApiDbProductsManager = new GeslibApiDbProductsManager();
		$queue = $this->getBatchFromQueue( (int) $batchSize, 'store_products' );
		foreach ( $queue as $task ) {
			if( $task->action == 'stock') {
				$geslibApiDbProductsManager->stockProduct( (int) $task->geslib_id, $task->data);
			} else if( $task->action == 'B') {
				$geslibApiDbProductsManager->deleteProduct( (int) $task->geslib_id );
			} else {
				$geslibApiDbProductsManager->storeProduct( (int) $task->geslib_id, $task->data );
			}
			$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, (int) $task->geslib_id );
		}
	}

	/**
	 * Processes a batch of tasks to store authors.
	 *
	 * @param int $batchSize The number of tasks to process in a batch. Default is 100.
	 * @return void
	 */
	public function processBatchStoreAutors( int $batchSize = 100 ) {
		$geslibApiDbManager = new GeslibApiDbManager();
		$geslibApiDbTaxonomyManager = new GeslibApiDbTaxonomyManager();
		$queue = $this->getBatchFromQueue( (int) $batchSize, 'store_autors' );
		
		foreach ( $queue as $task ) {
			if ( $task->action == 'B') {
				$geslibApiDbManager->deleteTerm( (int) $task->geslib_id, 'autors' );
			} else {
				$geslibApiDbTaxonomyManager->storeAuthor( (int) $task->geslib_id, $task->data );
			}
			$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, (int) $task->geslib_id );
		}
	}

	/**
	 * Processes a batch of tasks to store editorials.
	 *
	 * @param int $batchSize The number of tasks to process in a batch. Default is 100.
	 * @return void
	 */
	public function processBatchStoreEditorials( int $batchSize = 100 ) {
		$geslibApiDbManager = new GeslibApiDbManager();
		$geslibApiDbTaxonomyManager = new GeslibApiDbTaxonomyManager();
		$queue = $this->getBatchFromQueue( (int) $batchSize, 'store_editorials' );
		foreach ( $queue as $task ) {
			if( $task->action == 'B') {
				$geslibApiDbManager->deleteTerm( (int) $task->geslib_id, 'editorials' );
			} else {
				$geslibApiDbTaxonomyManager->storeEditorial( (int) $task->geslib_id, $task->data );
			}
			$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, (int) $task->geslib_id );
		}
	}

	/**
	 * Processes a batch of tasks to store categories.
	 *
	 * @param int $batchSize The number of tasks to process in a batch. Default is 100.
	 * @return void
	 */
	public function processBatchStoreCategories( int $batchSize = 100 ) {
		$geslibApiDbManager = new GeslibApiDbManager();
		$geslibApiDbTaxonomyManager = new GeslibApiDbTaxonomyManager();
		$queue = $this->getBatchFromQueue( (int) $batchSize, 'store_categories' );
		foreach ( $queue as $task ) {
			if ( $task->action == 'B') {
				$geslibApiDbManager->deleteTerm( (int) $task->geslib_id, "product_cat" );
			} else {
				$geslibApiDbTaxonomyManager->storeCategory( (int) $task->geslib_id, $task->data );
			}
			$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, (int) $task->geslib_id );
		}
	}

	/**
	 * Processes a batch of tasks to store colecciones.
	 *
	 * @param int $batchSize The number of tasks to process in a batch. Default is 100.
	 * @return void
	 */
	public function processBatchStoreColecciones( int $batchSize = 100 ) {
		$geslibApiDbManager = new GeslibApiDbManager();
		$geslibApiDbTaxonomyManager = new GeslibApiDbTaxonomyManager();
		$queue = $this->getBatchFromQueue( (int) $batchSize, 'store_colecciones' );
		foreach ( $queue as $task ) {
			if ( $task->action == 'B') {
				$geslibApiDbManager->deleteTerm( (int) $task->geslib_id, "colecciones" );
			} else {
				$geslibApiDbTaxonomyManager->storeColeccion( (int) $task->geslib_id, $task->data );
			}
			$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, (int) $task->geslib_id );
		}
	}

	/**
	 * getBatchFromQueue
	 *
	 * @param  int $batchSize
	 * @param  string $type
	 * @return array
	 */
	public function getBatchFromQueue( int $batchSize, string $type ): array {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT * FROM ". $wpdb->prefix . self::GESLIB_QUEUES_TABLE ."
            WHERE type=%s LIMIT %d",
            $type, $batchSize );
        return $wpdb->get_results( $query );
    }

	/**
	 * getQueuedTasks
	 *
	 * @param  string $type
	 * @return array
	 */
	public function getQueuedTasks( string $type) :array {
		global $wpdb;
		$query = $wpdb->prepare( "SELECT * FROM ". $wpdb->prefix . self::GESLIB_QUEUES_TABLE ." WHERE type=%s", $type );
        return $wpdb->get_results( $query );
	}

    /**
	 * countGeslibQueue
	 *
	 * @param  string $type
	 * @return mixed
	 */
	public function countGeslibQueue( string $type ): mixed {
		global $wpdb;
		$queueTable = $wpdb->prefix . self::GESLIB_QUEUES_TABLE; // Replace with your actual table name
		// Prepare SQL to count the number of each type of task
		$sql = $wpdb->prepare( "SELECT COUNT(*) FROM {$queueTable} WHERE type='%s'", $type);
		return $wpdb->get_var($sql);
	}
}