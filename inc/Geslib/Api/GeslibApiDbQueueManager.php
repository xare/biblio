<?php

namespace Inc\Geslib\Api;

use Inc\Biblio\Api\BiblioApi;
use Inc\Biblio\Api\DatabaseGuard;

class GeslibApiDbQueueManager extends GeslibApiDbManager {

	/**
	 * Default cap on the number of rows written with a single multi-row INSERT.
	 *
	 * Batches larger than this intentionally fall back to row-by-row inserts:
	 * one giant statement risks exceeding MySQL max_allowed_packet / SQL length
	 * limits, which would fail the entire batch instead of degrading gracefully.
	 * Tune at runtime with the 'biblio_geslib_multi_insert_threshold' filter.
	 */
	const MULTI_INSERT_THRESHOLD = 500;

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
			$result = DatabaseGuard::guard($wpdb->insert($wpdb->prefix . self::GESLIB_QUEUES_TABLE, $item, ['%d', '%d', '%s', '%s', '%s', '%s']), __METHOD__, 'geslib');
			if ( false === $result ) {
				$errors++;
			} else {
				$inserted++;
			}
		}
		if ($errors > 0) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "insertLinesIntoQueue: {$errors} errors out of " . count($batch) . " items", 'geslib');
		}
	}

	/**
	 * insertQueueRows
	 * Writes queue rows to the geslib queues table: one multi-row INSERT when the
	 * batch fits MULTI_INSERT_THRESHOLD, otherwise a row-by-row fallback.
	 * $wpdb never throws, so failures are detected via boolean returns.
	 *
	 * @param  array  $rows    List of rows, each keyed by log_id, geslib_id, entity, type, action, data.
	 * @param  string $context Short label identifying the caller in log messages.
	 * @return bool   True only when every row was inserted.
	 */
	private function insertQueueRows( array $rows, string $context ): bool {
		global $wpdb;
		if ( empty( $rows ) ) {
			return true;
		}

		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
		$threshold    = (int) apply_filters( 'biblio_geslib_multi_insert_threshold', self::MULTI_INSERT_THRESHOLD );
		$placeholders = [
			'log_id'    => '%d',
			'geslib_id' => '%d',
			'entity'    => '%s',
			'type'      => '%s',
			'action'    => '%s',
			'data'      => '%s',
		];

		// Normalise every row to the six queue columns, in that fixed order.
		$normalized = [];
		foreach ( $rows as $row ) {
			$normalized_row = [];
			foreach ( $placeholders as $column => $format ) {
				$value = $row[ $column ] ?? null;
				if ( is_object( $value ) || is_array( $value ) ) {
					$value = wp_json_encode( $value );
				}
				$normalized_row[ $column ] = $value;
			}
			$normalized[] = $normalized_row;
		}

		if ( count( $normalized ) <= $threshold ) {
			// Fast path: ONE multi-row INSERT statement.
			$columns = implode( ', ', array_keys( $placeholders ) );
			$tuples  = [];
			$values  = [];
			foreach ( $normalized as $row ) {
				$tuple = [];
				foreach ( $row as $column => $value ) {
					if ( null === $value ) {
						// Insert a real SQL NULL: a %s placeholder would store '' instead.
						$tuple[] = 'NULL';
						continue;
					}
					$tuple[]  = $placeholders[ $column ];
					$values[] = $value;
				}
				$tuples[] = '(' . implode( ',', $tuple ) . ')';
			}
			$sql      = "INSERT INTO {$queues_table} ({$columns}) VALUES " . implode( ', ', $tuples );
			$prepared = empty( $values ) ? $sql : $wpdb->prepare( $sql, $values );
			if ( false === $prepared || false === $wpdb->query( $prepared ) ) {
				$this->biblioApi->debug_log( 'ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
					"{$context}: multi-row insert of " . count( $normalized ) . " rows failed: " . $wpdb->last_error, 'geslib' );
				return false;
			}
			return true;
		}

		// Fallback: row-by-row inserts for oversized batches (avoids exceeding
		// max_allowed_packet / SQL length limits with one giant statement).
		$format = array_values( $placeholders );
		$failed = 0;
		foreach ( $normalized as $row ) {
			if ( false === $wpdb->insert( $queues_table, $row, $format ) ) {
				$failed++;
			}
		}
		if ( $failed > 0 ) {
			$this->biblioApi->debug_log( 'ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
				"{$context}: {$failed} of " . count( $normalized ) . " rows failed in row-by-row insert: " . $wpdb->last_error, 'geslib' );
			return false;
		}
		return true;
	}

    /**
     * insertProductsIntoQueue
     *
     * @param  array $batch
     * @return bool  True when every row was inserted and the bulk delete ran.
     */
    public function insertProductsIntoQueue( array $batch ): bool {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		// Batch INSERT: build rows array for single query
		$rows = [];
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
		}

		if ( ! $this->insertQueueRows( $rows, 'products' ) ) {
			return false;
		}
		$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." products into queue", 'geslib' );

		// Bulk DELETE: remove all corresponding build_content entries
		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$log_placeholders = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			DatabaseGuard::guard( $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$queues_table} WHERE entity = 'product' AND type = 'build_content' AND geslib_id IN ({$placeholders}) AND log_id IN ({$log_placeholders})",
				array_merge( $geslib_ids, $log_ids )
			) ), __METHOD__, 'geslib' );
		}
		return true;
	}

	/**
	 * insertAuthorsIntoQueue
	 *
	 * @param  array $batch
	 * @return bool  True when every row was inserted and the bulk delete ran.
	 */
	public function insertAuthorsIntoQueue( array $batch ): bool {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		$rows = [];
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
		}
		if ( ! $this->insertQueueRows( $rows, 'authors' ) ) {
			return false;
		}
		$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." authors into queue", 'geslib' );

		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$ph = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$lph = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			DatabaseGuard::guard( $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$queues_table} WHERE entity = 'autors' AND type = 'store_autors' AND geslib_id IN ({$ph}) AND log_id IN ({$lph})",
				array_merge( $geslib_ids, $log_ids )
			) ), __METHOD__, 'geslib' );
		}
		return true;
	}

	public function insertEditorialsIntoQueue( array $batch ): bool {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		$rows = [];
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
		}
		if ( ! $this->insertQueueRows( $rows, 'editorials' ) ) {
			return false;
		}
		$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." editorials into queue", 'geslib' );

		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$ph = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$lph = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			DatabaseGuard::guard( $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$queues_table} WHERE entity = 'editorial' AND type = 'store_editorials' AND geslib_id IN ({$ph}) AND log_id IN ({$lph})",
				array_merge( $geslib_ids, $log_ids )
			) ), __METHOD__, 'geslib' );
		}
		return true;
	}

	public function insertColeccionesIntoQueue( array $batch ): bool {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		$rows = [];
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
		}
		if ( ! $this->insertQueueRows( $rows, 'colecciones' ) ) {
			return false;
		}
		$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." colecciones into queue", 'geslib' );

		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$ph = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$lph = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			DatabaseGuard::guard( $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$queues_table} WHERE entity = 'coleccion' AND type = 'store_colecciones' AND geslib_id IN ({$ph}) AND log_id IN ({$lph})",
				array_merge( $geslib_ids, $log_ids )
			) ), __METHOD__, 'geslib' );
		}
		return true;
	}

	/**
	 * insertCategoriesIntoQueue
	 *
	 * @param  mixed $batch
	 * @return bool  True when every row was inserted and the bulk delete ran.
	 */
	public function insertCategoriesIntoQueue( array $batch ): bool {
		global $wpdb;
		$queues_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;

		$rows = [];
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
		}
		if ( ! $this->insertQueueRows( $rows, 'categories' ) ) {
			return false;
		}
		$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Batch inserted ".count($rows)." categories into queue", 'geslib' );

		$geslib_ids = array_column( $batch, 'geslib_id' );
		$log_ids    = array_unique( array_column( $batch, 'log_id' ) );
		if ( ! empty( $geslib_ids ) ) {
			$ph = implode( ',', array_fill( 0, count( $geslib_ids ), '%d' ) );
			$lph = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );
			DatabaseGuard::guard( $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$queues_table} WHERE entity = 'product_cat' AND type = 'store_categories' AND geslib_id IN ({$ph}) AND log_id IN ({$lph})",
				array_merge( $geslib_ids, $log_ids )
			) ), __METHOD__, 'geslib' );
		}
		return true;
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
		$result = DatabaseGuard::guard(
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
			),
			__METHOD__,
			'geslib'
		);
		return false !== $result;
	}

	 /**
     * deleteItemsFromQueue
     *
     * @param  string $type
     * @return bool
     */
    public function deleteItemsFromQueue( string $type ): bool {
		global $wpdb;
        // Delete query using Drupal's Database API
		$result = DatabaseGuard::guard(
			$wpdb->delete(
				$wpdb->prefix . self::GESLIB_QUEUES_TABLE,
				[
					'type' => $type,
				],
				[
					'%s', // placeholder for 'type'
				]
			),
			__METHOD__,
			'geslib'
		);
		return false !== $result;
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
			DatabaseGuard::guard( $wpdb->update( $table_name,
		 				['type' => $type],
						[
							'geslib_id' => $task->geslib_id,
							'entity' => $task->entity],
						'%s',
						['%d','%s'] ), __METHOD__, 'geslib' );
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