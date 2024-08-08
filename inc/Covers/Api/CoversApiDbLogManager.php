<?php

namespace Inc\Covers\Api;

class CoversApiDbLogManager extends CoversApiDbManager {
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
            $coversApiDbManager->countAllProducts(), //get_total_products
            0 // scanned_products
		];
		$insertArray = array_combine(self::$coversLogKeys, $coversLogValues);
		try {
			$wpdb->insert($wpdb->prefix . self::COVERS_LOG_TABLE,
						$insertArray,
						['%s', '%s', '%s', '%d', '%d']);
            return $wpdb->insert_id;
		} catch (\Exception $e) {
			error_log("[BIBLIO - Covers Api] This line has not been properly inserted into the database due to an error: ".$e->getMessage());
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
			wp_error('Unable to update the row.'.$exception->getMessage());
			return false;
		}
	}

}