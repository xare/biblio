<?php

namespace Inc\Geslib\Api;

use Inc\Biblio\Api\BiblioApi;
use Inc\Biblio\Api\DatabaseGuard;

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
	public function insertLogData( string $filename, string $status, int $linesCount, int $cycle = 1 ): bool {
		global $wpdb;
		$geslibLogValues = [
			$filename,
			$cycle,
			date('Y-m-d H:i:s'),
			null,
			$status,
			$linesCount,
		];
		$insertArray = array_combine(self::$geslibLogKeys, $geslibLogValues);
		$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Inserting the file $filename into geslib_log table", 'geslib');
		return DatabaseGuard::guard(
			$wpdb->insert($wpdb->prefix . self::GESLIB_LOG_TABLE,
				$insertArray,
				['%s', '%d', '%s', '%s', '%s', '%d']),
			__METHOD__,
			'geslib'
		);
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
		return DatabaseGuard::guard($wpdb->get_var($query), __METHOD__, 'geslib');

    }

    /**
     * getNextCycle
     * Returns the next cycle number for a given filename.
     * Called by GeslibApiReadFiles::_insert2geslibLog
     *
     * @param string $filename
     * @return int
     */
    public function getNextCycle( string $filename ): int {
        global $wpdb;
        $table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
        $query = $wpdb->prepare(
            "SELECT COALESCE(MAX(cycle), 0) + 1
             FROM {$table_name}
             WHERE filename = %s",
            $filename
        );
        return DatabaseGuard::guard($wpdb->get_var( $query ), __METHOD__, 'geslib');
    }

    /**
     * getLastStatusForFilename
     * Returns the status of the most recent record for a given filename.
     * Called by GeslibApiReadFiles::_insert2geslibLog
     *
     * @param string $filename
     * @return string|null
     */
    public function getLastStatusForFilename( string $filename ): ?string {
        global $wpdb;
        $table_name = $wpdb->prefix . self::GESLIB_LOG_TABLE;
        $query = $wpdb->prepare(
            "SELECT status
             FROM {$table_name}
             WHERE filename = %s
             ORDER BY cycle DESC
             LIMIT 1",
            $filename
        );
        return DatabaseGuard::guard($wpdb->get_var( $query ), __METHOD__, 'geslib');
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
		$result = DatabaseGuard::guard($wpdb->get_var($query), __METHOD__, 'geslib');
		if ( false === $result ) {
			return false;
		}
		return (string) $result;
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
								WHERE status IN (%s, %s)
								ORDER BY cycle ASC, id ASC
								LIMIT %d",
								'logged', 'queued', 1);
		$result = DatabaseGuard::guard($wpdb->get_var($query), __METHOD__, 'geslib');
		if ( false === $result ) {
			return false;
		}
		return ( $result !== null ) ? (int) $result : false;
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
		$result = DatabaseGuard::guard($wpdb->get_var($query), __METHOD__, 'geslib');
		if ( false === $result ) {
			return false;
		}
		return (string) $result;

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
								WHERE status IN (%s, %s)",
								'logged', 'queued' );
		return DatabaseGuard::guard($wpdb->get_var( $sql ), __METHOD__, 'geslib');
	}

	/**
	 * Retrieves the filename of the queued log.
	 *
	 * @return mixed The filename of the queued log, or another relevant value.
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
		return (string) (DatabaseGuard::guard($wpdb->get_var($sql), __METHOD__, 'geslib') == null) ? false : DatabaseGuard::guard($wpdb->get_var($sql), __METHOD__, 'geslib');
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
		$result = DatabaseGuard::guard($wpdb->query($sql), __METHOD__, 'geslib');
		return false !== $result;
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
		$query = $wpdb->prepare( "SELECT COUNT(id)
								FROM {$table_name}
								WHERE status = %s", $status);
		return DatabaseGuard::guard($wpdb->get_var( $query ), __METHOD__, 'geslib');
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
		return DatabaseGuard::guard($wpdb->get_var( "SELECT COUNT(id)
								FROM {$table_name}"), __METHOD__, 'geslib');

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
		$rows = DatabaseGuard::guard($wpdb->get_results( "SELECT filename, status
											FROM {$table_name}"), __METHOD__, 'geslib');
		return is_array($rows) ? $rows : [];

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
		$success = true;
		if ( false === DatabaseGuard::guard( $wpdb->query( 'DELETE FROM ' . $wpdb->prefix . self::GESLIB_LINES_TABLE . ' WHERE log_id IN (SELECT id FROM '.$wpdb->prefix.self::GESLIB_LOG_TABLE.')'), __METHOD__, 'geslib' ) ) {
			$success = false;
		}
		if ( false === DatabaseGuard::guard( $wpdb->query( 'ALTER TABLE ' . $wpdb->prefix . self::GESLIB_LINES_TABLE . ' AUTO_INCREMENT = 1' ), __METHOD__, 'geslib' ) ) {
			$success = false;
		}
		if ( false === DatabaseGuard::guard( $wpdb->query( 'SET FOREIGN_KEY_CHECKS=0;' ), __METHOD__, 'geslib' ) ) {
			$success = false;
		}
		if ( false === DatabaseGuard::guard( $wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::GESLIB_LOG_TABLE ), __METHOD__, 'geslib' ) ) {
			$success = false;
		}
		if ( false === DatabaseGuard::guard( $wpdb->query( 'SET FOREIGN_KEY_CHECKS=1;' ), __METHOD__, 'geslib' ) ) {
			$success = false;
		}
		global $wpdb;
		if ( ! empty( $wpdb->queries ) ) {
			foreach ( $wpdb->queries as $query ) {
				// Log each query to the debug log
				$this->biblioApi->debug_log( __CLASS__. ':'.__LINE__.' '.__FUNCTION__, $query[0] );
			}
		}
		return $success;
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
		$result = DatabaseGuard::guard(
			$wpdb->update( $table_name, $data, $where, $format, $where_format),
			__METHOD__,
			'geslib'
		);
		return false !== $result;
	}

	/**
	 * Retrieves the ID of the queued log entry.
	 *
	 * @return int|false Returns the log ID as an integer if found, or false if no queued log exists.
	 */
	public function getQueuedLogId(): int|false {
		global $wpdb;
		$table_name = $wpdb->prefix.self::GESLIB_LOG_TABLE;
		$query = $wpdb->prepare( "SELECT id
										FROM {$table_name}
										WHERE status = %s",'queued' );
		return DatabaseGuard::guard($wpdb->get_var($query), __METHOD__, 'geslib');

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
        return DatabaseGuard::guard($wpdb->get_var( $query ), __METHOD__, 'geslib');
    }
}