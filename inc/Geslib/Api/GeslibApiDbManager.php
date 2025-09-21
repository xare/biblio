<?php

namespace Inc\Geslib\Api;

use Exception;
use Inc\Biblio\Api\BiblioApi;
use Inc\Geslib\Api\GeslibApiSanitize;
use WC_Product_Simple;
use WP_Query;

class GeslibApiDbManager {
	/**
	 * Name of the database table used to store Geslib lines.
	 *
	 * @var string
	 */
	const GESLIB_LINES_TABLE = 'geslib_lines';
	/**
	 * Name of the database table used for logging Geslib API operations.
	 *
	 * @var string
	 */
	const GESLIB_LOG_TABLE = 'geslib_log';
	/**
	 * Name of the database table used for storing Geslib queue data.
	 *
	 * @var string
	 */
	const GESLIB_QUEUES_TABLE = 'geslib_queues';
	/**
	 * Static array containing the keys used for Geslib lines.
	 *
	 * @var array
	 */
	static $geslibLinesKeys = [
		'log_id', // int relation oneToMany with geslib_log
		'geslib_id', // int
		'filename', // string inter000
		'action', // string insert|update|delete
		'entity', // string product | category | author | publisher
		'content', // json
		'queued' // boolean 0|1
	];
	/**
	 * Static array containing the keys used for Geslib logging.
	 * These keys are utilized to identify and categorize log entries within the Geslib API database manager.
	 *
	 * @var array
	 */
	static $geslibLogKeys = [
		'filename', // string inter000
		'start_date', // date
		'end_date', // date
		'status', // string waiting | enqueued | processed
		'lines_count', // int number of lines
	];

	/**
	 * Instance of the Biblio API used for interacting with bibliographic data.
	 *
	 * @var mixed
	 */
	private $biblioApi;

	/**
	 * Constructor for the GeslibApiDbManager class.
	 * Initializes the database manager for the Geslib API integration.
	 */
    public function __construct() {
        $this->biblioApi = new BiblioApi;
    }

	/**
	 * countRows
	 * Count the number of rows in the geslib_log and geslib_lines tables
	 *
	 * @param  mixed $table
	 * @return int
	 */
	public function countRows( string $table ): int {
		global $wpdb;
		$table = $wpdb->prefix.'geslib_'.$table;
		return $wpdb->get_var( "SELECT COUNT(id) FROM $table" );
	}

    /**
     * _create_slug
	 * Called by GeslibApiDbTaxonomyManager
     *
     * @param  string $term_name
     * @return string
     */
    protected function _create_slug(string $term_name): string {
		// convert to lowercase
		$term_name = strtolower($term_name);

		// remove punctuation
		$term_name = preg_replace("/[.,:;!?(){}[\]<>%$#@^*+=|~`]/", "", $term_name);

		// replace spaces with underscores
		$term_slug = str_replace(" ", "_", $term_name);

		return (string) $term_slug;
  	}

	/**
	 * getGeslibTable
	 * Called by GeslibHelloController
	 *
	 * @param  string $table_suffix
	 * @return array
	 */
	public function getGeslibTable( string $table_suffix ): array {
		global $wpdb;
		$table_name = $wpdb->prefix .'geslib_'. $table_suffix;
		return $wpdb->get_results("SELECT * FROM {$table_name}", ARRAY_A);
    }

	/**
	 * Deletes a term from the database based on the provided Geslib ID and taxonomy name.
	 * Called by GeslibApiDbQueueManager.
	 * 
	 * @param int $geslib_id The Geslib ID of the term to delete.
	 * @param string $taxonomy_name The name of the taxonomy from which to delete the term.
	 * @return bool Returns true if the term was successfully deleted, false otherwise.
	 */
	public function deleteTerm( int $geslib_id, string $taxonomy_name ): bool {
		$taxonomy_prefix = match($taxonomy_name) {
			'autors' => 'autor_',
			'editorials' => 'editorial_',
			'product_cat' => 'category_',
			default => '',
		};
		$term = get_term_by($taxonomy_prefix.'geslib_id', $geslib_id, $taxonomy_name);
		if (false !== $term) {
			try {
				wp_delete_term($term->ID, $taxonomy_name);
				return true;
			} catch (Exception $e) {
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,'function deleteTerm : '. $e->getMessage(), 'geslib');
				return false;
			}
		}
		return false;
	}
}
