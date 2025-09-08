<?php

namespace Inc\Geslib\Api;

use Inc\Biblio\Api\BiblioApi;
use Inc\Geslib\Api\GeslibApiDbManager;

class GeslibApiDbLinesManager extends GeslibApiDbManager {

    private $biblioApi;

    public function __construct() {
        $this->biblioApi = new BiblioApi;
    }
    /**
	 * countGeslibLines
	 *
	 * @return int
	 */
	public function countGeslibLines(): int {
		global $wpdb;
		return $wpdb->get_var( "SELECT COUNT(*) FROM ".$wpdb->prefix.self::GESLIB_QUEUES_TABLE." WHERE type='build_content'");
	}

    /**
     * truncateGeslibLines
     *
     * @return bool
     */
    public function truncateGeslibLines(): bool {
		global $wpdb;
		try {
        	$wpdb->delete( $wpdb->prefix.self::GESLIB_QUEUES_TABLE, ['type' => 'store_lines'], ['%s'] );
			return true;
		} catch( \Exception $exception ) {
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__ , $exception->getMessage(), 'geslib');
			return false;
		}
	}

    /**
     * updateGeslibLines
     *
     * @param  int $geslib_id
     * @param  string $entity
     * @param  mixed $content
     * @return bool
     */
    public function updateGeslibLines( int $geslib_id, string $entity, mixed $content): bool{
		global $wpdb;
		try {
			$wpdb->update(
				$wpdb->prefix.self::GESLIB_QUEUES_TABLE,
				['data' => $content],
				[
					'geslib_id' => $geslib_id,
					'entity' => $entity,
                    'type' => 'build_content'
				],
				'%s',
				['%d','%s','%s']
			);
			return true;
		} catch( \Exception $exception ) {
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib');
			return false;
		}
	}

    /**
	 * insertData
	 *
	 * @param  mixed $content_array
	 * @param  string $action
	 * @param  int $log_id
	 * @param  string $entity
	 * @return bool
	 */
	public function insertData( mixed $content_array, string $action, int $log_id, string $entity ): bool {
		global $wpdb;
        $this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Inserting the $entity data into geslib lines", 'geslib');
        $this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Content array: " . print_r($content_array, true), 'geslib');
        $this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Log ID: " . $log_id, 'geslib');
        $this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Action: " . $action, 'geslib');
		try {
			$wpdb->insert(
				$wpdb->prefix.self::GESLIB_QUEUES_TABLE,
				[
					'log_id' => $log_id,
					'geslib_id' => $content_array['geslib_id'],
                    'type' => 'build_content',
					'action' => $action,
					'entity' => $entity,
					'data' => json_encode($content_array),
				],
			);
            return true;
		} catch (\Exception $e) {
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "The $entity data was NOT successfully inserted to geslib lines ".$e->getMessage(), 'geslib');
            return false;
		}
	}

    /**
     * fetchContent
     *
     * @param  int $geslib_id
     * @param  string $entity
     * @return ?string
     */
    public function fetchContent( int $geslib_id, string $entity ): ?string {
		global $wpdb;
		$table = $wpdb->prefix.self::GESLIB_QUEUES_TABLE;
		$query = $wpdb->prepare(
							"SELECT
								data
							FROM {$table}
							WHERE
								geslib_id = '%d'
							AND
								entity = '%s'
                            AND
                                type = '%s'",
						$geslib_id, $entity, 'build_content');
		return $wpdb->get_var( $query );
	}

    /**
     * getAuthorsFromGeslibLines
     * Get Authors from Geslib Lines
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @return mixed Array of authors or false on failure.
     */
    public function getAuthorsFromGeslibLines(): mixed {
        global $wpdb;
        $queue_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
        // Prepare the SQL query. Ensure your column names are correct.
        $query = "SELECT * FROM {$queue_table} WHERE entity = '%s' AND TYPE='%s'";
        $prepared_query = $wpdb->prepare($query, 'autors', 'store_autors');
        try {
            // Execute the query and get the results.
			// ARRAY_A returns the result as an associative array.
            $results = $wpdb->get_results($prepared_query, ARRAY_A);
            return $results;
        } catch (\Exception $exception) {
            // Log the error to WordPress debug log.
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Function getAuthorsFromGeslibLines: ' .$exception->getMessage(), 'geslib');
            return false;
        }
    }

	/**
     * getEditorialsFromGeslibLines
     * Get Editorials from Geslib Lines
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @return mixed Array of authors or false on failure.
     */
    public function getEditorialsFromGeslibLines(): mixed {
        global $wpdb;
        $queue_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
        // Prepare the SQL query. Ensure your column names are correct.
        $query = "SELECT * FROM {$queue_table} WHERE type='store_lines' AND entity = '%s' AND TYPE='%s'";
        $prepared_query = $wpdb->prepare($query, 'editorial', 'store_editorial');

        try {
            // Execute the query and get the results.
			// ARRAY_A returns the result as an associative array.
            $results = $wpdb->get_results($prepared_query, ARRAY_A);
            return $results;
        } catch (\Exception $exception) {
            // Log the error to WordPress debug log.
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Function getEditorialsFromGeslibLines: ' .$exception->getMessage(), 'geslib');
            return false;
        }
    }

    /**
     * getColeccionesFromGeslibLines
     * Get Colecciones from Geslib Lines
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @return mixed Array of colecciones or false on failure.
     */
    public function getColeccionesFromGeslibLines(): mixed {
        global $wpdb;
        $queue_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
        // Prepare the SQL query. Ensure your column names are correct.
        $query = "SELECT * FROM {$queue_table} WHERE type='store_lines' AND entity = '%s' AND TYPE='%s'";
        $prepared_query = $wpdb->prepare($query, 'coleccion', 'store_colecciones');

        try {
            // Execute the query and get the results.
			// ARRAY_A returns the result as an associative array.
            $results = $wpdb->get_results($prepared_query, ARRAY_A);
            return $results;
        } catch (\Exception $exception) {
            // Log the error to WordPress debug log.
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,'Function getColeccionesFromGeslibLines: ' . $exception->getMessage(), 'geslib');
            return false;
        }
    }


	/**
     * getCategoriesFromGeslibLines
     * Get Categories from Geslib Lines
     *
     * @global wpdb $wpdb WordPress database abstraction object.
     * @return mixed Array of categories or false on failure.
     */
    public function getCategoriesFromGeslibLines(): mixed {
        global $wpdb;
        $queue_table = $wpdb->prefix . self::GESLIB_QUEUES_TABLE;
        // Prepare the SQL query. Ensure your column names are correct.
        $query = "SELECT * FROM {$queue_table} WHERE  entity = '%s' AND type='%s'";
        $prepared_query = $wpdb->prepare($query, 'product_cat', 'store_categories');

        try {
            // Execute the query and get the results.
			// ARRAY_A returns the result as an associative array.
            return $wpdb->get_results($prepared_query, ARRAY_A);
        } catch (\Exception $exception) {
            // Log the error to WordPress debug log.
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Function getCategoriesFromGeslibLines: ' . $exception->getMessage(), 'geslib');
            return false;
        }
    }
}