<?php

namespace Inc\Covers\Api;

use Inc\Biblio\Api\BiblioApi;

class CoversApiDbLogManager extends CoversApiDbManager {

	private $biblioApi;

    public function __construct() {
        $this->biblioApi = new BiblioApi;
    }
    /**
	 * insertLogData
	 *
	 * @param  string $filename
	 * @param  string $status
	 * @param  int $linesCount
	 * @return int|false The number or rows inserted on false on error.
	 */
	public function insertLogData( string $status) :mixed {
		global $wpdb;
		$coversApiDbManager = new CoversApiDbManager;
		$coversLogValues = [
			date('Y-m-d H:i:s'), // start_date
			null, // end_date
			$status, // status
            count($coversApiDbManager->getProductsWithoutCover()), //get_total_products
            0 // processed_products
		];
		$insertArray = array_combine(self::$coversLogKeys, $coversLogValues);
		try {
			$wpdb->insert($wpdb->prefix . self::COVERS_LOG_TABLE,
						$insertArray,
						['%s', '%s', '%s', '%d', '%d']);
            return $wpdb->insert_id;
		} catch (\Exception $e) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "This line has not been properly inserted into the database due to an error: ".$e->getMessage(), 'covers');
            return false;
        }
	}

    /**
	 * updateLogStatus
	 *
	 * @param  int $log_id
	 * @param  string $status
	 * @return bool
	 */
	public function setLogStatus( int $log_id, string $status ) :bool {
		global $wpdb;
		$table_name = $wpdb->prefix.self::COVERS_LOG_TABLE; // Replace with your actual table name if different
		$data = [ 'status' => $status ];
		if( $status == 'processed' ) {
			$data[ 'end_date' ] = date('Y-m-d H:i:s');
		}
		$where = ['id' => $log_id];
		$format = ['%s']; // string format
		$where_format = ['%d']; // integer format
		try {
			$wpdb->update( $table_name, $data, $where, $format, $where_format);
			return true;
		} catch( \Exception $exception ) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Unable to update the row.'.$exception->getMessage(), 'covers');
			return false;
		}
	}

	/**
	 * Updates the number of scanned products in the database.
	 *
	 * @param int $log_id The ID of the log.
	 * @param int $scanned_products The number of scanned products.
	 * @return bool Returns true if the update is successful, false otherwise.
	 */

	public function set_processed_products(int $log_id, int $processed_items): bool {
		global $wpdb;
		$table_name = $wpdb->prefix.self::COVERS_LOG_TABLE;
		$data = [ 'processed_items' => $processed_items ];
		$where = ['id' => $log_id];
		$format = ['%d']; // integer format
		$where_format = ['%d']; // integer format
		try {
			$wpdb->update( $table_name, $data, $where, $format, $where_format);
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'We updated the covers_log table with '.$processed_items.' scanned products.', 'covers');
			return true;
		} catch( \Exception $exception ) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Unable to update the row. '.$exception->getMessage(), 'covers');
			return false;
		}
	}
}