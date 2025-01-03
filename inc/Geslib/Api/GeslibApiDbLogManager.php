<?php

namespace Inc\Geslib\Api;

use Inc\Biblio\Api\BiblioApi;

class GeslibApiDbLogManager extends GeslibApiDbManager {
	private $biblioApi;

    public function __construct() {
        $this->biblioApi = new BiblioApi;
    }
    /**
	 * insertLogData
	 * Called by
	 * GeslibApiRead Files _insert2geslibLog
	 *
	 * @param  string $filename
	 * @param  string $status
	 * @param  int $linesCount
	 * @return bool
	 */
	public function insertLogData( string $filename, string $status, int $linesCount ): bool {
		global $wpdb;
		$geslibLogValues = [
			$filename,
			date('Y-m-d H:i:s'),
			null,
			$status,
			$linesCount,
		];
		$insertArray = array_combine(self::$geslibLogKeys, $geslibLogValues);
		try {
			$wpdb->insert($wpdb->prefix . self::GESLIB_LOG_TABLE,
						$insertArray,
						['%s', '%s', '%s', '%s', '%d']);
			return true;
		} catch (\Exception $e) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "This file has not been properly inserted into the database due to an error: ".$e->getMessage(), 'geslib');
			return false;
		}
	}

    /**
	 * isFilenameExists
     * Check if the filename exists in the wpgeslib_log table.
	 * Called by:
	 * - GeslibApiReadFiles function _insert2geslibLog
     *
     * @param string $filename
     * @return bool
     */
    public function isFilenameExists( string $filename ): bool {
        global $wpdb;
		$table_name = $wpdb->prefix.self::GESLIB_LOG_TABLE;
		$query = $wpdb->prepare(
            "SELECT COUNT(ID)
			FROM {$table_name}
			WHERE filename = %s",
            $filename
        );
		try {
			return (bool) $wpdb->get_var($query) > 0;
		} catch(\Exception $e) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "This file has not been found into the database due to an error: ".$e->getMessage(), 'geslib');
			return false;
		}

    }

    /**
	 * getLogQueuedFile
	 * Called by GeslibApiLog.php function getQueuedFile()
	 *
	 * @return string|false
	 */
	public function getLogQueuedFile(): string|false {
		global $wpdb;
		$table_name = $wpdb->prefix.self::GESLIB_LOG_TABLE;
		$query = $wpdb->prepare(
            "SELECT filename
			FROM {$table_name}
			WHERE status = '%s'",
            'queued'
        );
		try {
        	return (string) $wpdb->get_var($query);
		} catch(\Exception $e) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "This file has not been found into the database due to an error: ".$e->getMessage(), 'geslib');
			return false;
		}
	}

    /**
	 * getGeslibLoggedId
	 *
	 * Returns $log_id from the lowest row with status "logged".
	 * Called by:
	 * - Cron.php
	 * - GeslibStoreProductsFormController.php
	 * - GeslibLinesCommand.php
	 * - GeslibProcessAllCommand.php
	 * - GeslibStoreProductsCommand.php
	 *
	 * @return int|false
	 */
	public function getGeslibLoggedId(): int|false {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
		$query = $wpdb->prepare("SELECT
									id
								FROM {$table_name}
								WHERE status=%s
								ORDER BY id ASC
								LIMIT %d",
								['logged', 1]);
		try {
			return (int) $wpdb->get_var($query);
		} catch(\Exception $e) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "This file has not been found into the database due to an error: ".$e->getMessage(), 'geslib');
			return false;
		}
	}

    /**
	 * getGeslibLoggedFilename
	 * Called by:
	 * - GeslibApiLines.php
	 *
	 * @param int
	 * @return string|false
	 */
	public function getGeslibLoggedFilename( int $log_id ): string|false {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
		$query = $wpdb->prepare("SELECT
									filename
								FROM {$table_name}
								WHERE id = %d",
								$log_id);
		try {
			return (string) $wpdb->get_var($query);
		}
		catch(\Exception $e) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "This file has not been found into the database due to an error: ".$e->getMessage(), 'geslib');
			return false;
		}

	}

    /**
	 * Checks if there is at least one "logged" status in geslib_log table.
	 * Called by:
	 * - Cron.php
	 * - GeslibStoreProductsFormController
	 * - GeslibProcessAllCommand
	 * - GeslibStoreProductsCommand
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return bool
	 *   Returns true if there is at least one row with status "logged",
	 *   false otherwise.
	 */
	function checkLoggedStatus(): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
		$sql = $wpdb->prepare( "SELECT COUNT(*)
								FROM {$table_name}
								WHERE status = %s
								ORDER BY id=%s",
								['logged', 'ASC'] );
		try {
			return (bool) $wpdb->get_var( $sql ) > 0;
		} catch(\Exception $e) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "This file has not been found into the database due to an error: ".$e->getMessage(), 'geslib');
			return false;
		}
	}

    /**
     * getLogQueuedFilename
     *
     * @return mixed
     */
    public function getLogQueuedFilename(): mixed {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
		$sql = $wpdb->prepare(
			"SELECT filename
			FROM {$table_name}
            WHERE status = %s LIMIT %d",
			['queued', 1 ]
		);
		try {
			return (string) ($wpdb->get_var($sql) == null) ? false : $wpdb->get_var($sql);
		} catch(\Exception $e) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "This file has not been found into the database due to an error: ".$e->getMessage(), 'geslib');
			return false;
		}
	}

    /**
	 * setLogTableToLogged
	 * Sets the status of all rows in the geslib_log table to "logged".
	 * Called by:
	 * - GeslibStoreProductsFormController.php
	 *
	 * @return bool
	 */
	public function setLogTableToLogged(): bool {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
		// SQL to update the status
		$sql = "UPDATE {$table_name} SET status = 'logged'";

		// Execute the query
		try {
			$wpdb->query($sql);
			return true;
		} catch (\Exception $exception) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib');
			return false;
		}
	}

    /**
	 * countGeslibLogStatus
	 * Called by:
	 * - GeslibUpdateValuesController
	 * - tab1_content.php
	 *
	 * @param  mixed $status
	 * @return int|false
	 */
	public function countGeslibLogStatus( string $status ): int|false {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
		try {
			$query = $wpdb->prepare( "SELECT COUNT(id)
								FROM {$table_name}
								WHERE status = %s", $status);
			return (int) $wpdb->get_var( $query );
		} catch(\Exception $exception) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib');
			return false;
		}
	}

    /**
	 * countGeslibLog
	 * Called by:
	 * - GeslibUpdateValuesController
	 * - tab1_content.php
	 *
	 * @return int
	 */
	public function countGeslibLog(): int {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
		try {
			return (int) $wpdb->get_var( "SELECT COUNT(id)
								FROM {$table_name}");
		} catch(\Exception $exception) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib');
			return false;
		}

	}

    /**
	 * fetchLoggedFilesFromDb
	 * Called by:
	 * - GeslibStoreProductsFormController
	 *
	 * @return array|false
	 */
	public function fetchLoggedFilesFromDb(): array|false {
		global $wpdb;
		$table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
		try {
			return (array) $wpdb->get_results( "SELECT filename, status
											FROM {$table_name}");
		} catch (\Exception $exception) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'geslib');
			return false;
		}

	}

    /**
     * truncateGeslibLogs
	 * Truncates geslib_log table
	 * Called by:
	 * - GeslibProductsFormController
     *
     * @return bool
     */
    public function truncateGeslibLogs(): bool {
		global $wpdb;
		try {
			$wpdb->query( 'DELETE FROM ' . $wpdb->prefix . self::GESLIB_LINES_TABLE . ' WHERE log_id IN (SELECT id FROM '.$wpdb->prefix.self::GESLIB_LOG_TABLE.')');
			$wpdb->query( 'ALTER TABLE ' . $wpdb->prefix . self::GESLIB_LINES_TABLE . ' AUTO_INCREMENT = 1' );
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS=0;' );
			$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::GESLIB_LOG_TABLE );
			$wpdb->query( 'SET FOREIGN_KEY_CHECKS=1;' );
			global $wpdb;
			if ( ! empty( $wpdb->queries ) ) {
				foreach ( $wpdb->queries as $query ) {
					// Log each query to the debug log
					$this->biblioApi->debug_log( __CLASS__. ':'.__LINE__.' '.__FUNCTION__, $query[0] );
				}
			}
			return true;
		} catch( \Exception $exception ) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Unable to truncate geslib_lines table' .$exception->getMessage(), 'geslib');
			return false;
		}
	}

    /**
	 * updateLogStatus
	 * Called by:
	 * - GeslibApiLog
	 * - Cron
	 * - GeslibStoreProductsFormController
	 * - GeslibLinesCommand
	 * - GeslibProcessAllCommand
	 * - GeslibStoreProdcutsCommand
	 *
	 * @param  int $log_id
	 * @param  string $status
	 * @return bool
	 */
	public function setLogStatus( int $log_id, string $status ): bool {
		global $wpdb;
		$table_name = $wpdb->prefix.self::GESLIB_LOG_TABLE; // Replace with your actual table name if different
		$data = [];
		$data['status'] = $status ;
		$data['end_date'] = ( $status == 'processed' ) ? date('Y-m-d H:i:s', time()): null;
		$where = ['id' => $log_id];
		$format = ['%s', '%s']; // string format
		$where_format = ['%d']; // integer format
		try {
			$wpdb->update( $table_name, $data, $where, $format, $where_format);
			return true;
		} catch( \Exception $exception ) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Unable to update the row'.$exception->getMessage(), 'geslib');
			return false;
		}
	}

	/**
	 * getQueuedLogId
	 *
	 * @return int|false
	 */
	public function getQueuedLogId(): int|false {
		global $wpdb;
		$table_name = $wpdb->prefix.self::GESLIB_LOG_TABLE;
		try {
			$query = $wpdb->prepare( "SELECT id
										FROM {$table_name}
										WHERE status = %s",'queued' );
			return (int) $wpdb->get_var($query);
		} catch ( \Exception $exception) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'ERROR on getQueuedLogId: '. $exception->getMessage(), 'geslib');
			return false;
		}

	}

    /**
	* isQueued
	*
	* Checks if there are any queued items in the database.
	* Called by:
	* - Cron
	* - GeslibStoreProductsFormController
	* - GeslibProcessAllCommand
	* - GeslibStoreProductsCommand
	*
	* @global wpdb $wpdb WordPress database abstraction object.
	* @return bool True if there are queued items, false otherwise.
	*/
    public function isQueued(): bool {
        global $wpdb;
		$table_name = $wpdb->prefix.self::GESLIB_LOG_TABLE;
        // Prepare the SQL query. Ensure your column names are correct.
        $query = $wpdb->prepare( "SELECT id
									FROM $table_name
									WHERE status = %s LIMIT %d", ['queued', 1] );
        // Return true if a result is found, false otherwise.
        try {
			return !is_null( $wpdb->get_var( $query ) );
		} catch( \Exception $exception ) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Unable to check if there are any queued items'.$exception->getMessage(), 'geslib');
			return false;
		}
    }
}