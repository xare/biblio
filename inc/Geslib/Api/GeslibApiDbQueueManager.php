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
		$inserted = 0;
		$errors = 0;
		foreach ($batch as $item) {
			try {
				$wpdb->insert($wpdb->prefix . self::GESLIB_QUEUES_TABLE, $item, ['%d', '%d', '%s', '%s', '%s', '%s']);
				$inserted++;
			} catch( \Exception $exception ) {
				$errors++;
            }
		}
		if ($errors > 0) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "insertLinesIntoQueue: {$errors} errors out of " . count($batch) . " items", 'geslib');
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

		// Batch INSERT: build rows array for single query
		$rows = [];
		$format = [];
		foreach ( $batch as $item ) {
			$data = is_object( $item['data'] ) ? wp_json_encode( $item['data'] ) : $item['data'];
			$rows[] = [
				'log_id'    => $item['log_id'],
				'geslib_id' => $item['geslib_id'],
				'entity'    => $item['entity'],
				'type'      => $item['type'],
				'action'    => isset( $item['action'] ) ? $item['action'] : null,
				'data'      => $data,
			];
			$format[] = '%d';
			$format[] = '%d';
			$format[] = '%s';
			$format[] = '%s';
			$format[] = '%s';
			$format[] = '%s';
		}

		try {
			$wpdb->insert( $queues_table, $rows, $format );
			$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." products into queue", 'geslib' );
		} catch ( \Exception $exception ) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch insert failed: ".$exception->getMessage(), 'geslib' );
			return;
		}

		// Bulk DELETE: remove all corresponding build_content entries
		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$log_placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			try {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$queues_table} WHERE entity = 'product' AND type = 'build_content' AND geslib_id IN ({$placeholders}) AND log_id IN ({$log_placeholders})",
					array_merge( $geslib_ids, $log_ids )
				) );
			} catch ( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Bulk delete failed: ".$exception->getMessage(), 'geslib' );
			}
		}
	}

	/**
	 * insertAuthorsIntoQueue
	 *
	 * @param  array $batch
	 * @return void
	 */
	public function insertAuthorsIntoQueue( array $batch ): void {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		$rows = [];
		$format = [];
		foreach ( $batch as $item ) {
			$data = is_object( $item['data'] ) ? wp_json_encode( $item['data'] ) : $item['data'];
			$rows[] = [
				'log_id'    => $item['log_id'],
				'geslib_id' => $item['geslib_id'],
				'entity'    => $item['entity'] ?? 'autors',
				'type'      => $item['type'] ?? 'store_autors',
				'action'    => isset( $item['action'] ) ? $item['action'] : null,
				'data'      => $data,
			];
			$format[] = '%d'; $format[] = '%d'; $format[] = '%s';
			$format[] = '%s'; $format[] = '%s'; $format[] = '%s';
		}
		try {
			$wpdb->insert( $queues_table, $rows, $format );
			$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." authors into queue", 'geslib' );
		} catch ( \Exception $exception ) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch insert failed: ".$exception->getMessage(), 'geslib' );
			return;
		}

		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$ph = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$lph = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			try {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$queues_table} WHERE entity = 'autors' AND type = 'store_autors' AND geslib_id IN ({$ph}) AND log_id IN ({$lph})",
					array_merge( $geslib_ids, $log_ids )
				) );
			} catch ( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Bulk delete failed: ".$exception->getMessage(), 'geslib' );
			}
		}
	}

	public function insertEditorialsIntoQueue( array $batch ) {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		$rows = [];
		$format = [];
		foreach ( $batch as $item ) {
			$data = is_object( $item['data'] ) ? wp_json_encode( $item['data'] ) : $item['data'];
			$rows[] = [
				'log_id'    => $item['log_id'],
				'geslib_id' => $item['geslib_id'],
				'entity'    => $item['entity'] ?? 'editorial',
				'type'      => $item['type'] ?? 'store_editorials',
				'action'    => isset( $item['action'] ) ? $item['action'] : null,
				'data'      => $data,
			];
			$format[] = '%d'; $format[] = '%d'; $format[] = '%s';
			$format[] = '%s'; $format[] = '%s'; $format[] = '%s';
		}
		try {
			$wpdb->insert( $queues_table, $rows, $format );
			$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." editorials into queue", 'geslib' );
		} catch ( \Exception $exception ) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch insert failed: ".$exception->getMessage(), 'geslib' );
			return;
		}

		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$ph = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$lph = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			try {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$queues_table} WHERE entity = 'editorial' AND type = 'store_editorials' AND geslib_id IN ({$ph}) AND log_id IN ({$lph})",
					array_merge( $geslib_ids, $log_ids )
				) );
			} catch ( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Bulk delete failed: ".$exception->getMessage(), 'geslib' );
			}
		}
	}

	public function insertColeccionesIntoQueue( array $batch ) {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		$rows = [];
		$format = [];
		foreach ( $batch as $item ) {
			$data = is_object( $item['data'] ) ? wp_json_encode( $item['data'] ) : $item['data'];
			$rows[] = [
				'log_id'    => $item['log_id'],
				'geslib_id' => $item['geslib_id'],
				'entity'    => $item['entity'] ?? 'coleccion',
				'type'      => $item['type'] ?? 'store_colecciones',
				'action'    => isset( $item['action'] ) ? $item['action'] : null,
				'data'      => $data,
			];
			$format[] = '%d'; $format[] = '%d'; $format[] = '%s';
			$format[] = '%s'; $format[] = '%s'; $format[] = '%s';
		}
		try {
			$wpdb->insert( $queues_table, $rows, $format );
			$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." colecciones into queue", 'geslib' );
		} catch ( \Exception $exception ) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch insert failed: ".$exception->getMessage(), 'geslib' );
			return;
		}

		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$ph = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$lph = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			try {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$queues_table} WHERE entity = 'coleccion' AND type = 'store_colecciones' AND geslib_id IN ({$ph}) AND log_id IN ({$lph})",
					array_merge( $geslib_ids, $log_ids )
				) );
			} catch ( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Bulk delete failed: ".$exception->getMessage(), 'geslib' );
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

		$rows = [];
		$format = [];
		foreach ( $batch as $item ) {
			$data = is_object( $item['data'] ) ? wp_json_encode( $item['data'] ) : $item['data'];
			$rows[] = [
				'log_id'    => $item['log_id'],
				'geslib_id' => $item['geslib_id'],
				'entity'    => $item['entity'] ?? 'product_cat',
				'type'      => $item['type'] ?? 'store_categories',
				'action'    => isset( $item['action'] ) ? $item['action'] : null,
				'data'      => $data,
			];
			$format[] = '%d'; $format[] = '%d'; $format[] = '%s';
			$format[] = '%s'; $format[] = '%s'; $format[] = '%s';
		}
		try {
			$wpdb->insert( $queues_table, $rows, $format );
			$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." categories into queue", 'geslib' );
		} catch ( \Exception $exception ) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch insert failed: ".$exception->getMessage(), 'geslib' );
			return;
		}

		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$ph = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$lph = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			try {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$queues_table} WHERE entity = 'product_cat' AND type = 'store_categories' AND geslib_id IN ({$ph}) AND log_id IN ({$lph})",
					array_merge( $geslib_ids, $log_ids )
				) );
			} catch ( \Exception $exception ) {
				$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Bulk delete failed: ".$exception->getMessage(), 'geslib' );
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
	 * @param  string $type      Queue type to process
	 * @param  int    $maxBatches Max batches to process (0 = unlimited, process all)
	 * @return bool
	 */
	public function processFromQueue( string $type, int $maxBatches = 0 ): bool {
		
		global $wpdb;
        $table_name = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		$preparedQuery1 = $wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE `type` = %s", $type);
		$queue_count1 = $wpdb->get_var($preparedQuery1);

		if($queue_count1 == 0) {
			return false;
		}

		$startTime = microtime(true);
		$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Queue {$type}: {$queue_count1} items", 'geslib');
		
		$methodName = 'processBatch' . str_replace('_', '', ucwords($type, '_'));
		if (method_exists($this, $methodName)) {
			$batchesRun = 0;
			do {
				$this->$methodName(3000);
				$batchesRun++;
				// Heartbeat: refresh cron lock after each batch so it doesn't go stale
				set_transient( 'geslib_cron_lock', time(), 3600 );
				$preparedQuery = $wpdb->prepare("SELECT COUNT(*) FROM `$table_name` WHERE `type` = %s", $type);
				$queue_count = $wpdb->get_var($preparedQuery);
			} while ($queue_count > 0 && ($maxBatches == 0 || $batchesRun < $maxBatches));
			$elapsed = round(microtime(true) - $startTime, 1);
			$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Queue {$type}: {$queue_count} items remaining ({$batchesRun} batches, {$elapsed}s)", 'geslib');
			return $queue_count == 0;
		} else {
			$this->biblioApi->getLogger()->error('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Unrecognized queue type: ' . $type, 'geslib' );
			return false;
		}
	}

	/**
	 * processBatchBuildContent
	 *
	 * @param  int $batchSize
	 * @return void
	 */
	public function processBatchBuildContent( int $batchSize = 100 ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		$batchSize = min((int) $batchSize, 200);
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
		// Cap batch size — 200 lines per batch balances speed and memory
		$batchSize = min((int) $batchSize, 200);
        $queue = $this->getBatchFromQueue( $batchSize, 'store_lines' );
        $geslibApiLines = new GeslibApiLines();
		$count = 0;
        foreach ($queue as $task) {
            $geslibApiLines->readLine( $task->data, (int) $task->log_id );
			$count++;
        }
		$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Processed {$count} store_lines items", 'geslib');
    }

	/**
	 * processBatchStoreProducts
	 *
	 * @param  int $batchSize
	 * @return void
	 */
	public function processBatchStoreProducts( int $batchSize = 100 ) {
		$geslibApiDbProductsManager = new GeslibApiDbProductsManager();
		$startTime = microtime(true);
		// Cap batch size — 50 products per batch balances speed and server timeout
		// Each product requires ~15 DB queries (WC save + meta + taxonomy),
		// so 50 products ≈ 750 queries ≈ 60-90s, safe for most server timeouts
		$batchSize = min((int) $batchSize, 50);
		$queue = $this->getBatchFromQueue( $batchSize, 'store_products' );
		$totalTasks = count($queue);
		
		// Separate tasks by type
		$stockTasks = [];
		$deleteTasks = [];
		$buildTasks = [];
		
		foreach ( $queue as $task ) {
			if( $task->action == 'stock') {
				$stockTasks[] = $task;
			} else if( $task->action == 'B') {
				$deleteTasks[] = $task;
			} else {
				$buildTasks[] = $task;
			}
		}
		
		$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
			"Processing {$totalTasks} products (stock:" . count($stockTasks) .
			" delete:" . count($deleteTasks) .
			" build:" . count($buildTasks) . ")", 'geslib');
		
		// Pre-load product ID map ONCE for deletes AND builds
		$productIdMap = $geslibApiDbProductsManager->loadProductIdMap();
		
		// Batch process stock updates (1 query for all products)
		if (!empty($stockTasks)) {
			$stockMap = $geslibApiDbProductsManager->loadStockMap();
			$changedStocks = [];
			foreach ($stockTasks as $task) {
				$data = json_decode($task->data, true);
				$newStock = isset($data['stock']) ? $data['stock'] : 0;
				$changedStocks[(string) $task->geslib_id] = $newStock;
			}
			$count = $geslibApiDbProductsManager->batchUpdateStock($changedStocks, $stockMap);
			$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Stock: {$count} updated", 'geslib');
			foreach ($stockTasks as $task) {
				$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, (int) $task->geslib_id );
			}
		}
		
		// Process delete tasks using pre-loaded product ID map (instant lookup, no WP_Query)
		if (!empty($deleteTasks)) {
			$deleted = 0;
			foreach ($deleteTasks as $task) {
				$geslibId = (string) $task->geslib_id;
				if (isset($productIdMap[$geslibId])) {
					$postId = (int) $productIdMap[$geslibId];
					$product = wc_get_product($postId);
					if ($product) {
						$product->delete(true);
						$deleted++;
					}
				}
				$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, (int) $task->geslib_id );
			}
			$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Deleted: {$deleted} products", 'geslib');
		}
		
		// Batch process product creates/updates (pre-loads remaining maps once)
		if (!empty($buildTasks)) {
			$editorialMap = $geslibApiDbProductsManager->loadEditorialTermMap();
			$authorMap = $geslibApiDbProductsManager->loadAuthorTermMap();
			$categoryMap = $geslibApiDbProductsManager->loadCategoryTermMap();
			
			$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
				"Maps: " . count($productIdMap) . " products, " .
				count($editorialMap) . " editorials, " .
				count($authorMap) . " authors, " .
				count($categoryMap) . " categories", 'geslib');
			
			$count = $geslibApiDbProductsManager->batchStoreProducts(
				$buildTasks,
				$productIdMap,
				$editorialMap,
				$authorMap,
				$categoryMap
			);
			
			$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Built: {$count} products", 'geslib');
			
			foreach ($buildTasks as $task) {
				$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, (int) $task->geslib_id );
			}
		}
		
		$elapsed = round(microtime(true) - $startTime, 1);
		$mem = round(memory_get_peak_usage(true) / 1048576, 1);
		$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
			"Batch complete: {$totalTasks} products in {$elapsed}s (peak: {$mem}MB)", 'geslib');
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
		$batchSize = min((int) $batchSize, 200);
		$queue = $this->getBatchFromQueue( $batchSize, 'store_autors' );
		$count = 0;
		$deleted = 0;
		foreach ( $queue as $task ) {
			if ( $task->action == 'B') {
				$geslibApiDbManager->deleteTerm( (int) $task->geslib_id, 'autors' );
				$deleted++;
			} else {
				$geslibApiDbTaxonomyManager->storeAuthor( (int) $task->geslib_id, $task->data );
			}
			$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, (int) $task->geslib_id );
			$count++;
		}
		$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Authors: {$count} processed, {$deleted} deleted", 'geslib');
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
		$batchSize = min((int) $batchSize, 200);
		$queue = $this->getBatchFromQueue( $batchSize, 'store_editorials' );
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
		$batchSize = min((int) $batchSize, 200);
		$queue = $this->getBatchFromQueue( $batchSize, 'store_categories' );
		foreach ( $queue as $task ) {
			if ( $task->action == 'B') {
				$geslibApiDbManager->deleteTerm( $task->geslib_id, "product_cat" );
			} else {
				$geslibApiDbTaxonomyManager->storeCategory( $task->geslib_id, $task->data );
			}
			$this->deleteItemFromQueue( (string) $task->type, (int) $task->log_id, $task->geslib_id );
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
		$batchSize = min((int) $batchSize, 200);
		$queue = $this->getBatchFromQueue( $batchSize, 'store_colecciones' );
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

	/**
	 * Count queue items by type AND action (e.g. stock updates within store_products)
	 */
	public function countGeslibQueueByAction( string $type, string $action ): int {
		global $wpdb;
		$queueTable = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$queueTable} WHERE type=%s AND action=%s",
			$type, $action
		);
		return (int) $wpdb->get_var($sql);
	}

	/**
	 * Check if queue has items for a specific log_id
	 */
	public function hasQueueItemsForLog( int $log_id ): bool {
		global $wpdb;
		$queueTable = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		$sql = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$queueTable} WHERE log_id=%d",
			$log_id
		);
		return (int) $wpdb->get_var($sql) > 0;
	}
}