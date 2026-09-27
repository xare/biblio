<?php

namespace Inc\Geslib\Api;

use Inc\Biblio\Api\BiblioApi;
use WP_Query;
use WC_Product_Simple;

class GeslibApiDbProductsManager extends GeslibApiDbManager {

	/**
	 * Instance of the Biblio API used for interacting with bibliographic data.
	 *
	 * @var mixed $biblioApi
	 */
	private $biblioApi;

	/**
	 * Constructor for GeslibApiDbProductsManager.
	 *
	 * Initializes the class instance and sets up any required dependencies or properties.
	 */
    public function __construct() {
        $this->biblioApi = new BiblioApi;
    }
    /**
     * storeProducts
     *
     * @return void
     */
    public function storeProducts() {
		global $wpdb;
        $geslibApiDbQueueManager = new GeslibApiDbQueueManager;
		$geslibQueuesTable = $wpdb->prefix.self::GESLIB_QUEUES_TABLE;

		// Create a queue for storing products.
        $actions = [
            'A', // Add
			'M', // Modify
            'B'  // Delete
        ];

		foreach ( $actions as $actionSet ) {
			$query = $wpdb->prepare(
				"SELECT * FROM {$geslibQueuesTable}
				WHERE action='%s'
				AND entity='%s'
				AND type='%s'",
				[ $actionSet, 'product', 'build_content']
			);
			$lines = $wpdb->get_results( $query );
			$batch_size = 3000; // Choose a reasonable batch size
			$batch = [];
			foreach ( $lines as $line ) {
				$item = [
					'log_id' => $line->log_id,
					'geslib_id' => $line->geslib_id,
					'entity' => $line->entity,
					'type' => 'store_products',  // type to identify the task in processQueue
				];
				if(isset($line->data->action)) {
					$item['action'] = $line->data->action;
				}
				$item['data'] = $line->data;
				$batch[] = $item;
				if ( count( $batch ) >= $batch_size ) {
					$geslibApiDbQueueManager->insertProductsIntoQueue( $batch );
					$batch = [];
				}
			}
			// Don't forget the last batch
			if ( !empty( $batch ) ) {
				$geslibApiDbQueueManager->insertProductsIntoQueue( $batch );
			}
		}
	}

    /**
     * storeProduct
     *
     * @param  int $geslib_id
     * @param  string $content
     * @return int
     */
    public function storeProduct( int $geslib_id, string $content ): int {
		// Check if product already exists
		$content = json_decode( $content, true );
		$ean = isset($content['ean']) ? $content['ean'] : '';
		$author = isset($content['author']) ? $content['author'] : '';
		$num_paginas = isset($content['num_paginas'])? $content['num_paginas'] : 0;
		$editorial_geslib_id = isset($content['editorial']) ? $content['editorial']:'';
		$book_name = isset($content['description']) ? $content['description']:'';;
		$peso = isset($content['peso']) ? $content['peso']/1000 : 0;
		$book_subtitle = isset($content['subtitulo']) ? $content['subtitulo'] : '';
		$book_description = '';
		$stock = isset($content['stock']) ? $content['stock'] : 0;

		if ( isset( $content['sinopsis'] ) )
			$book_description = isset( $content['sinopsis']) ? $content['sinopsis'] : '';

			$book_price = ( isset( $content['pvp'] ) && $content['pvp'] != null ) ? floatval(str_replace(',', '.', $content['pvp'])) : 0.00;
			$existing_product = null;
			$args = [
				'post_type'      => 'product',
				'posts_per_page' => 1,
				'meta_query'     => [
					[
						'key'   => 'geslib_id',
						'value' => $geslib_id,
					],
				],
			];

			$products = new WP_Query($args);

			if($products->have_posts()) {
				while ($products->have_posts()) {
					$products->the_post();
					$existing_product = $products->post;
				}
				wp_reset_postdata();
			}

			if ($existing_product) {
				// If product exists, get an instance of WC_Product for the existing product
				$product = wc_get_product($existing_product->ID);
			} else {
				// If product does not exist, create a new instance of WC_Product_Simple
				$product = new WC_Product_Simple;
				$product->set_name($book_name); // name is only set for new products
			}

			// Set or update product data

			$product->set_description($book_description);
			$product->set_status("publish");  // can also be 'draft' or 'pending'
			$product->set_catalog_visibility('visible');  // or 'hidden'
			$product->set_price($book_price);
			$product->set_regular_price($book_price);
			$product->set_weight($peso);
			$product->set_manage_stock( true );
			$product->set_stock_quantity($stock);

			// ... Set other product properties

			// Save the product to the database and get its ID
			try {
				$product_id = $product->save();
			} catch(\Exception $exception) {
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage() , 'geslib');
			}

			if( isset($ean) ){
				update_post_meta($product_id, '_ean', $ean);
				update_post_meta($product_id, '_num_paginas', $num_paginas);
			}
			if( isset($book_subtitle) && $book_subtitle !== '' ) {
				update_post_meta($product_id, '_subtitle', $book_subtitle);
			}
			if( isset($author) ) {
				update_post_meta($product_id, '_author', $author);
			}
			update_post_meta($product_id, 'geslib_id', $geslib_id);
			// Get the integer value from the content array
			$editorial_id = (isset($content['editorial'])) ? intval($content['editorial']) : 0;

			// Get terms
			$args = [
				'taxonomy' => 'editorials', // the taxonomy for the term
				'hide_empty' => false, // also retrieve terms which are not used yet
				'meta_query' => [
						['key'       => 'editorial_geslib_id', // your meta key
						'value'     => $editorial_id, // your meta value
						'compare'   => '='],
					],
				];
			$terms = get_terms($args);
			// Check if any term found
			if (!empty($terms) && !is_wp_error($terms)) {
				// Terms found, get the first term
				$editorial_term = $terms[0]->term_id;
				// Assign the product to the editorial taxonomy term
				try {
					wp_set_object_terms($product_id, $editorial_term, 'editorials', true);
				} catch(\Exception $exception) {
					$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage() , 'geslib');
				}
			}

		// APPEND AUTHORS
		if (isset($content['authors']) && is_array($content['authors']) && count($content['authors']) > 0) {
			$author_ids = [];
			foreach ($content['authors'] as $key => $value) {
				$author_id = intval($key);
				$aut_args = [
					'taxonomy' => 'autors', // the taxonomy for the term
					'hide_empty' => false, // also retrieve terms which are not used yet
					'meta_query' => [
							['key'   => 'author_geslib_id', // your meta key
							'value'  => $author_id, // your meta value
							'compare'=> '='],
						],
					];
				$authors = get_terms($aut_args);
				if (!empty($authors) && !is_wp_error($authors)) {
					// Terms found, get the first term
					$author_term = $authors[0]->term_id;
					// Assign the product to the editorial taxonomy term
					try {
						wp_set_object_terms( $product_id, $author_term, 'autors', true );
					} catch( \Exception $exception ){
						$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Failed to store author with id'. $author_term.' to product with id'. $product_id.': '.$exception->getMessage(), 'geslib');
						continue;
					}
				}
			}
		}
		// APPEND CATEGORIES
		if( isset($content['categories']) && is_array($content['categories']) && count( $content['categories']) > 0 ) {
			foreach ( $content['categories'] as $key => $value ) {
				$category_id = (string) $key;
				// Get terms
				$cat_args = [
					'taxonomy' => 'product_cat', // the taxonomy for the term
					'hide_empty' => false, // also retrieve terms which are not used yet
					'meta_query' => [
							'key'   => 'category_geslib_id', // your meta key
							'value'  => $category_id, // your meta value
							'compare'=> '=',
						],
					];

				$categories = get_terms($cat_args);
				// Check if any term found
				// Example for categories
				if (!empty($categories) && !is_wp_error($categories)) {
					$category_terms = wp_list_pluck($categories, 'term_id');

					// Remove 'Uncategorized' category if other categories exist
					if(get_term_by('slug', 'uncategorized', 'product_cat')) {
						$uncategorized_term_id = get_term_by('slug', 'uncategorized', 'product_cat')->term_id;
						if (in_array($uncategorized_term_id, $category_terms)) {
							if (count($category_terms) > 1) { // Check if there are other categories assigned
								$key = array_search($uncategorized_term_id, $category_terms);
								unset($category_terms[$key]);
							}
						}
					}

					// Removes previously assigned categories.
					$existing_terms = wp_get_object_terms($product_id, 'product_cat', ['fields' => 'ids']);
					// Check if there are any existing terms
					if (!empty($existing_terms)) {
						// Remove all existing terms for the 'product_cat' taxonomy
						try {
							wp_remove_object_terms($product_id, $existing_terms, 'product_cat');
							$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Terms '. var_export($existing_terms, true) .' were removed from product: '. $product_id , 'geslib');
						} catch(\Exception $exception) {
							$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Terms were not properly removed from product: '. $exception->getMessage() , 'geslib');
							continue;
						}	
					}
					foreach ($category_terms as $category_term) {
						// Assign each category to the product, excluding 'Uncategorized' if applicable
						try {
							wp_set_object_terms($product_id, $category_term, 'product_cat', true);
						} catch(\Exception $exception) {
							$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Term was not properly assigned to product: '. $exception->getMessage() , 'geslib');
							continue;
						}
					}
				}
			}
		}
		return $product_id;
    }

	// Helper function to get the product ID by 'geslib_id'
	/**
	 * Retrieves the WooCommerce product ID associated with a given Geslib ID.
	 *
	 * This function searches for a WooCommerce product that matches the provided Geslib ID
	 * and returns its product ID if found.
	 *
	 * @param string|int $geslib_id The Geslib ID to search for.
	 * @return int|null The WooCommerce product ID if found, or null if not found.
	 */
	function wc_get_product_id_by_geslib_id( $geslib_id ) {
		global $wpdb;

		// Query the database to find the product ID based on 'geslib_id' meta key
		$product_id = $wpdb->get_var( $wpdb->prepare("
			SELECT post_id 
			FROM $wpdb->postmeta 
			WHERE meta_key = 'geslib_id' 
			AND meta_value = %s
			LIMIT 1
		", $geslib_id) );

		return $product_id ? intval( $product_id ) : false;
	}

	/**
	 * Updates the stock information for a product identified by its Geslib ID.
	 *
	 * @param int   $geslib_id The unique identifier of the product in Geslib.
	 * @param mixed $data      The stock data to be updated for the product.
	 *
	 * @return void
	 */
    public function stockProduct( int $geslib_id, $data ): void {
		$data = json_decode($data);
        $stock = $data->stock;
        if($stock == null || $stock == 0) return;
        // Ensure that WooCommerce is active
        if ( ! function_exists( 'wc_get_product' ) ) {
           return;
       }

       // Args for the WP_Query
       $args = [
           'post_type'      => 'product',
           'posts_per_page' => 1,
           'meta_query'     => [
               [
                   'key'   => 'geslib_id',
                   'value' => $geslib_id,
               ],
           ],
       ];

       // Get the product
       $query = new WP_Query( $args );

       if ( $query->have_posts() ) {
           while ( $query->have_posts() ) {
               $query->the_post();
               $product_id = get_the_ID();
               $product = wc_get_product( $product_id );
			   if( $product->get_stock_quantity() != $stock ) continue;
               if ( $product) {
                   // Update the stock
                   $product->set_stock_quantity( $stock );
                   try {
						$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Setting Stock for geslib_id '.$geslib_id.' to: ' . $stock);
						$product->save();
				   } catch( \Exception $exception ) {
						$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Product stock for geslib_id '.$geslib_id.' has NOT been updated:'. $exception->getMessage(), 'geslib');
						continue;
				   }
			   } 
           }
       }

       // Reset the global post data. This restores the $post global to the current post in the main query.
       wp_reset_postdata();
   }

	/**
	 * Retrieves the total number of products from the database.
	 *
	 * @return int The total count of products.
	 */
	public function getTotalNumberOfProducts(): int {
		global $wpdb;

		// Get the total number of products (excluding variations)
		$total_products = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" );

		// Get the total number of product variations (if needed)
		$total_variations = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product_variation' AND post_status = 'publish'" );

		// Sum the products and variations if variations should be included in the total
		$total = $total_products + $total_variations;

		return $total;
	}

	/**
	 * Deletes all products from the database.
	 *
	 * @return mixed The result of the delete operation.
	 */
	public function deleteAllProducts(): mixed {
		// Query for all products
		$batch_size = (!isset($_POST['batch_size']) || $_POST['batch_size'] == null) ? -1 : $_POST['batch_size'];
		$offset = (!isset($_POST['offset']) || $_POST['offset'] == null) ? 0 : $_POST['offset'];

		$args = [
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
		];

		$query = new WP_Query( $args );
		$totalLines = $query->found_posts;
		$processedLines = 0;
		$hasMore = !empty($product_geslib_lines);

		// If no posts are returned, we're done
		if ( !$query->have_posts() ) {
			return null;
		}
		$loop = 0;
		$response = [];
		// Loop through all products and delete
		while ( $query->have_posts() ) {
			$query->the_post();
			$id = get_the_ID();
			wp_delete_post( $id, true );
			$processedLines++;
			$progress = ($processedLines / $totalLines) * 100;
			update_option('geslib_delete_product_progress', $progress);
			if ( $loop == 0 ) {
				$response['title'] = "DELETING PRODUCTS";
			}
			$loop++;
		}

		// Reset query data
		wp_reset_postdata();
		$response['hasMore'] = $hasMore;
		$response['progress'] = $progress;
		$response['totalLines'] = $totalLines;
		$response['message'] = "Processed {$processedLines} products.";
		return json_encode( $response );
	}

	/**
	 * Deletes a product from the database using its Geslib ID.
	 *
	 * @param int $geslib_id The unique identifier of the product in Geslib.
	 * @return bool Returns true if the product was successfully deleted, false otherwise.
	 */
    public function deleteProduct( int $geslib_id ): bool {
		$args = [
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'   => 'geslib_id',
					'value' => $geslib_id,
				],
			],
		];
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();

				// Using WooCommerce CRUD functions to delete product
				$product = wc_get_product( $post_id );
				try{
					$product->delete( true );
				} catch(\Exception $exception) {
					$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Product has NOT been deleted:'. $exception->getMessage(), 'geslib');
					continue;
				}
			}
			wp_reset_postdata();
			return true;
		} else {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'No products found with the given geslib_id '.$geslib_id, 'geslib');
			return false;
		}
	}

	/**
	 * Checks if a product identified by its Geslib ID has at least the specified stock.
	 *
	 * @param int $geslib_id The Geslib ID of the product to check.
	 * @param int $stock The minimum stock quantity to verify (default is 0).
	 * @return bool Returns true if the product has at least the specified stock, false otherwise.
	 */
	public function check_product_stock_by_geslib_id($geslib_id, $stock = 0) :bool {
		// Fetch the product using the previous function
		$product = $this->get_product_by_geslib_id($geslib_id);
	
		if ($product) {
			return (bool) $stock != $product->get_stock_quantity();
		}
	
		return false; // Return false if no product found
	}
	
	/**
	 * Pre-loads ALL product stocks into a lookup map for batch processing.
	 * 
	 * Returns an associative array keyed by geslib_id:
	 * [ '17859' => { post_id: 42, current_stock: '5' }, ... ]
	 * 
	 * @return array
	 */
	public function loadStockMap(): array {
		global $wpdb;

		$results = $wpdb->get_results("
			SELECT 
				pm_geslib.post_id,
				pm_geslib.meta_value AS geslib_id,
				pm_stock.meta_value AS current_stock
			FROM {$wpdb->postmeta} pm_geslib
			INNER JOIN {$wpdb->postmeta} pm_stock 
				ON pm_geslib.post_id = pm_stock.post_id 
				AND pm_stock.meta_key = '_stock'
			WHERE pm_geslib.meta_key = 'geslib_id'
		", OBJECT_K );

		return $results ?: [];
	}

	/**
	 * Pre-loads ALL geslib_id → post_id mappings for batch product lookups.
	 *
	 * @return array [ '17859' => 42, ... ]
	 */
	public function loadProductIdMap(): array {
		global $wpdb;

		$results = $wpdb->get_results("
			SELECT meta_value AS geslib_id, post_id
			FROM {$wpdb->postmeta}
			WHERE meta_key = 'geslib_id'
		", OBJECT_K );

		$map = [];
		foreach ($results as $geslib_id => $row) {
			$map[$geslib_id] = (int) $row->post_id;
		}
		return $map;
	}

	/**
	 * Pre-loads ALL editorial terms (editorial_geslib_id → term_id).
	 *
	 * @return array [ '123' => 456, ... ]
	 */
	public function loadEditorialTermMap(): array {
		global $wpdb;

		$results = $wpdb->get_results("
			SELECT tm.meta_value AS geslib_id, t.term_id
			FROM {$wpdb->termmeta} tm
			INNER JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
			WHERE tm.meta_key = 'editorial_geslib_id'
			AND tt.taxonomy = 'editorials'
		");

		$map = [];
		if ($results) {
			foreach ($results as $row) {
				$map[$row->geslib_id] = (int) $row->term_id;
			}
		}
		return $map;
	}

	/**
	 * Pre-loads ALL author terms (author_geslib_id → term_id).
	 *
	 * @return array [ '123' => 456, ... ]
	 */
	public function loadAuthorTermMap(): array {
		global $wpdb;

		$results = $wpdb->get_results("
			SELECT tm.meta_value AS geslib_id, t.term_id
			FROM {$wpdb->termmeta} tm
			INNER JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
			WHERE tm.meta_key = 'author_geslib_id'
			AND tt.taxonomy = 'autors'
		");

		$map = [];
		if ($results) {
			foreach ($results as $row) {
				$map[$row->geslib_id] = (int) $row->term_id;
			}
		}
		return $map;
	}

	/**
	 * Pre-loads ALL category terms (category_geslib_id → term_id).
	 *
	 * @return array [ '123' => 456, ... ]
	 */
	public function loadCategoryTermMap(): array {
		global $wpdb;

		$results = $wpdb->get_results("
			SELECT tm.meta_value AS geslib_id, t.term_id
			FROM {$wpdb->termmeta} tm
			INNER JOIN {$wpdb->terms} t ON t.term_id = tm.term_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
			WHERE tm.meta_key = 'category_geslib_id'
			AND tt.taxonomy = 'product_cat'
		");

		$map = [];
		if ($results) {
			foreach ($results as $row) {
				$map[$row->geslib_id] = (int) $row->term_id;
			}
		}
		return $map;
	}

	/**
	 * Checks if stock has changed using a pre-loaded map (no DB query).
	 *
	 * @param string $geslib_id The Geslib ID to check.
	 * @param string $new_stock The new stock value from the B-line.
	 * @param array $stockMap Pre-loaded stock map from loadStockMap().
	 * @return bool True if stock changed, false otherwise.
	 */
	public function stockHasChanged(string $geslib_id, string $new_stock, array $stockMap): bool {
		if (!isset($stockMap[$geslib_id])) {
			return false; // Product not found, skip
		}
		return (int) $stockMap[$geslib_id]->current_stock !== (int) $new_stock;
	}

	/**
	 * Batch-updates stock for multiple products in a single DB operation.
	 *
	 * @param array $changedStocks [geslib_id => new_stock, ...]
	 * @param array $stockMap Pre-loaded stock map from loadStockMap().
	 * @return int Number of products updated.
	 */
	public function batchUpdateStock(array $changedStocks, array $stockMap): int {
		global $wpdb;
		$count = 0;

		// Group updates by value to reduce individual UPDATE queries
		$byValue = [];
		foreach ($changedStocks as $geslib_id => $newStock) {
			if (!isset($stockMap[$geslib_id])) continue;
			$product_id = (int) $stockMap[$geslib_id]->post_id;
			$byValue[(int) $newStock][] = $product_id;
		}

		foreach ($byValue as $stockValue => $productIds) {
			$placeholders = implode(',', array_fill(0, count($productIds), '%d'));
			$wpdb->query($wpdb->prepare(
				"UPDATE {$wpdb->postmeta} 
				SET meta_value = %s 
				WHERE post_id IN ($placeholders) 
				AND meta_key = '_stock'",
				array_merge([$stockValue], $productIds)
			));
			$count += count($productIds);
		}

		return $count;
	}

	/**
	 * Batch-processes multiple products using pre-loaded maps.
	 * Reduces DB queries from ~15 per product to ~1 per product (only $product->save()).
	 *
	 * @param array $tasks Array of queue task objects with geslib_id, data, action
	 * @param array $productIdMap Pre-loaded geslib_id → post_id map
	 * @param array $editorialMap Pre-loaded editorial_geslib_id → term_id map
	 * @param array $authorMap Pre-loaded author_geslib_id → term_id map
	 * @param array $categoryMap Pre-loaded category_geslib_id → term_id map
	 * @return int Number of products processed
	 */
	public function batchStoreProducts(
		array $tasks,
		array $productIdMap,
		array $editorialMap,
		array $authorMap,
		array $categoryMap
	): int {
		global $wpdb;

		$count = 0;
		$total = count($tasks);
		$uncategorizedTermId = null;

		// Pre-load uncategorized term once
		$uncategorized = get_term_by('slug', 'uncategorized', 'product_cat');
		if ($uncategorized) {
			$uncategorizedTermId = $uncategorized->term_id;
		}

		foreach ($tasks as $task) {
			$content = json_decode($task->data, true);
			if (!$content) {
				$count++;
				continue;
			}

			$geslib_id = (int) $task->geslib_id;
			$ean = isset($content['ean']) ? $content['ean'] : '';
			$author = isset($content['author']) ? $content['author'] : '';
			$num_paginas = isset($content['num_paginas']) ? $content['num_paginas'] : 0;
			$book_name = isset($content['description']) ? $content['description'] : '';
			$peso = isset($content['peso']) ? $content['peso'] / 1000 : 0;
			$book_subtitle = isset($content['subtitulo']) ? $content['subtitulo'] : '';
			$book_description = isset($content['sinopsis']) ? $content['sinopsis'] : '';
			$stock = isset($content['stock']) ? $content['stock'] : 0;
			$book_price = (isset($content['pvp']) && $content['pvp'] != null)
				? floatval(str_replace(',', '.', $content['pvp']))
				: 0.00;

			// 1. Find or create product using pre-loaded map (O(1) lookup)
			$product_id = isset($productIdMap[$geslib_id]) ? $productIdMap[$geslib_id] : 0;
			if ($product_id) {
				$product = wc_get_product($product_id);
			} else {
				$product = new \WC_Product_Simple;
				$product->set_name($book_name);
			}

			if (!$product) continue;

			// 2. Set product data
			$product->set_description($book_description);
			$product->set_status("publish");
			$product->set_catalog_visibility('visible');
			$product->set_price($book_price);
			$product->set_regular_price($book_price);
			$product->set_weight($peso);
			$product->set_manage_stock(true);
			$product->set_stock_quantity($stock);

			// 3. Save product (unavoidable — triggers WC hooks)
			try {
				$product_id = $product->save();
			} catch (\Exception $exception) {
				$this->biblioApi->debug_log(
					__CLASS__ . ':' . __LINE__ . ' ' . __FUNCTION__,
					$exception->getMessage(),
					'geslib'
				);
				continue;
			}

			// 4. Batch meta updates — single query to fetch existing, then bulk upsert
			$metaUpdates = [];
			if ($ean) {
				$metaUpdates[] = ['meta_key' => '_ean', 'meta_value' => $ean];
				$metaUpdates[] = ['meta_key' => '_num_paginas', 'meta_value' => $num_paginas];
			}
			if ($book_subtitle !== '') {
				$metaUpdates[] = ['meta_key' => '_subtitle', 'meta_value' => $book_subtitle];
			}
			if ($author) {
				$metaUpdates[] = ['meta_key' => '_author', 'meta_value' => $author];
			}
			// Always update geslib_id
			$metaUpdates[] = ['meta_key' => 'geslib_id', 'meta_value' => $geslib_id];

			if ( ! empty( $metaUpdates ) ) {
				// Fetch all existing meta for this product in ONE query
				$meta_keys = array_column( $metaUpdates, 'meta_key' );
				$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );
				$existing_rows = $wpdb->get_results( $wpdb->prepare(
					"SELECT meta_id, meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ({$placeholders})",
					array_merge( [ $product_id ], $meta_keys )
				) );
				$existing_map = [];
				foreach ( $existing_rows as $row ) {
					$existing_map[ $row->meta_key ] = $row->meta_id;
				}

				// Split into updates and inserts
				$to_update = [];
				$to_insert = [];
				foreach ( $metaUpdates as $meta ) {
					if ( isset( $existing_map[ $meta['meta_key'] ] ) ) {
						$to_update[] = $meta;
						$to_update_ids[] = $existing_map[ $meta['meta_key'] ];
					} else {
						$to_insert[] = $meta;
					}
				}

				// Bulk update existing meta
				foreach ( $to_update as $i => $meta ) {
					$wpdb->update(
						$wpdb->postmeta,
						[ 'meta_value' => $meta['meta_value'] ],
						[ 'meta_id' => $existing_map[ $meta['meta_key'] ] ],
						[ '%s' ],
						[ '%d' ]
					);
				}

				// Bulk insert new meta
				foreach ( $to_insert as $meta ) {
					$wpdb->insert(
						$wpdb->postmeta,
						[
							'post_id'   => $product_id,
							'meta_key'  => $meta['meta_key'],
							'meta_value' => $meta['meta_value'],
						],
						[ '%d', '%s', '%s' ]
					);
				}
			}

			// 5. Assign editorial (from pre-loaded map)
			$editorial_id = isset($content['editorial']) ? intval($content['editorial']) : 0;
			if ($editorial_id && isset($editorialMap[$editorial_id])) {
				wp_set_object_terms($product_id, $editorialMap[$editorial_id], 'editorials', true);
			}

			// 6. Assign authors (from pre-loaded map)
			if (isset($content['authors']) && is_array($content['authors'])) {
				foreach ($content['authors'] as $author_geslib_id => $value) {
					$author_id = intval($author_geslib_id);
					if (isset($authorMap[$author_id])) {
						wp_set_object_terms($product_id, $authorMap[$author_id], 'autors', true);
					}
				}
			}

			// 7. Assign categories (from pre-loaded map)
			if (isset($content['categories']) && is_array($content['categories'])) {
				$categoryTermIds = [];
				foreach ($content['categories'] as $cat_geslib_id => $value) {
									$cat_id = (string) $cat_geslib_id;
					if (isset($categoryMap[$cat_id])) {
						$categoryTermIds[] = $categoryMap[$cat_id];
					}
				}

				if (!empty($categoryTermIds)) {
					// Remove uncategorized if other categories exist
					if ($uncategorizedTermId && in_array($uncategorizedTermId, $categoryTermIds)) {
						if (count($categoryTermIds) > 1) {
							$key = array_search($uncategorizedTermId, $categoryTermIds);
							unset($categoryTermIds[$key]);
						}
					}

					// Remove existing categories and assign new ones
					$existing_terms = wp_get_object_terms($product_id, 'product_cat', ['fields' => 'ids']);
					if (!empty($existing_terms)) {
						wp_remove_object_terms($product_id, $existing_terms, 'product_cat');
					}

					foreach ($categoryTermIds as $term_id) {
						wp_set_object_terms($product_id, $term_id, 'product_cat', true);
					}
				}
			}

			$count++;
			if ($count % 50 === 0) {
				$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Progress: {$count}/{$total} products", 'geslib');
			}
		}

		return $count;
	}

	/**
	 * Retrieves a product from the database using its Geslib ID.
	 *
	 * @param mixed $geslib_id The Geslib ID of the product to retrieve.
	 * @return mixed The product data associated with the given Geslib ID, or null if not found.
	 */
	private function get_product_by_geslib_id($geslib_id): mixed {
		// Query products based on custom field
		$args = array(
			'meta_key'   => 'geslib_id',   // Your custom meta key
			'meta_value' => $geslib_id,    // The value of your custom meta
			'post_type'  => 'product',
			'posts_per_page' => 1,         // Limit to 1 product
		);
	
		$query = new WP_Query($args);
	
		if ($query->have_posts()) {
			$query->the_post(); // Get the first product
			$product_id = get_the_ID();
			wp_reset_postdata();
			return wc_get_product($product_id);
		}
	
		wp_reset_postdata();
		return false;
	}
	
}