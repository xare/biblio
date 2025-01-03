<?php

namespace Inc\Covers\Api;

use Inc\Biblio\Api\BiblioApi;

class CoversApiDbLinesManager extends CoversApiDbManager {

    private $biblioApi;

    public function __construct() {
        $this->biblioApi = new BiblioApi;
    }
    public function insertLinesData(
                        int $log_id,
                        string $isbn,
                        string $path = '',
                        string $type = 'dilve',
                        string $url_origin = '',
                        string $url_target = '',
                        string $error = '',
                        int $attempts = 0,
                          ) :mixed {
		global $wpdb;
        $table_name = $wpdb->prefix.self::COVERS_LINES_TABLE; // Replace with your actual table name if different
		$coversLinesValues = [
			$log_id,
            $isbn,
            $path,
            $url_origin,
            $url_target,
            date('Y-m-d H:i:s'), // start_date
            false,
			$error, // error
            $attempts, // scanned_products
            $type, // dilve|cegal
		];
		$insertArray = array_combine(self::$coversLinesKeys, $coversLinesValues);

        //check if the line already exists and then decide whether INSERT or UPDATE
        $line_id = $this->getLineId($isbn);
		try {
            if( $line_id ) {
                $sql = "UPDATE $table_name SET attempts = attempts + 1 WHERE id = $line_id";
                $wpdb->query($sql);
                return $line_id;
            } else {
                $wpdb->insert($wpdb->prefix . self::COVERS_LINES_TABLE,
						$insertArray,
						['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%d', '%s']);
                return $wpdb->insert_id;
            }
		} catch (\Exception $e) {
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "This line has not been properly inserted into the database due to an error: ".$e->getMessage(), 'covers');
            return false;
        }
	}

    /**
     * setError
     *
     * @param  string $filename
     * @param  string $error
     * @return void
     */
    public function setError( string $filename, string $error ) {
        global $wpdb;
        $id = $this->getLineId($filename);

        $table_name = $wpdb->prefix.self::COVERS_LINES_TABLE; // Replace with your actual table name if different
        $data = [ 'isError' => true, 'error' => $error ];
        $where = ['id' => $id];
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
     * Updates the url_origin field in the database table for a given id.
     *
     * @param int $id The id of the row to update.
     * @param string $origin_url The new value for the url_origin field.
     * @throws \Exception If the update operation fails.
     * @return bool True if the update is successful, false otherwise.
     */

    public function set_url_origin( int $id, string $origin_url ): bool {
        global $wpdb;
        $table_name = $wpdb->prefix.self::COVERS_LINES_TABLE; // Replace with your actual table name if different
        $data = [ 'url_origin' => $origin_url ];
        $where = ['id' => $id];
        $format = ['%s']; // string format
        $where_format = ['%d']; // integer format
        try {
            $wpdb->update( $table_name, $data, $where, $format, $where_format);
            return true;
        } catch( \Exception $exception ) {
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'covers');
            return false;
        }
    }

    public function getLineId($filename) {
        $isbn = basename($filename, '.jpg');
        global $wpdb;
        $table_name = $wpdb->prefix.self::COVERS_LINES_TABLE; // Replace with your actual table name if different
        $sql = "SELECT id FROM $table_name WHERE isbn = '$isbn'";
        $id = $wpdb->get_var($sql);
        return $id;
    }

    public function setBook($title, $book_id, $line_id) {
        global $wpdb;
        $table_name = $wpdb->prefix.self::COVERS_LINES_TABLE; // Replace with your actual table name if different
        $data = [ 'booktitle' => $title, 'book_id' => $book_id ];
        $where = ['id' => $line_id];
        $format = ['%s', '%d']; // string format
        $where_format = ['%d']; // integer format
        try {
            $wpdb->update( $table_name, $data, $where, $format, $where_format);
            return true;
        } catch( \Exception $exception ) {
            $this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $exception->getMessage(), 'covers');
            return false;
        }
    }

    public function set_url_target(int $line_id, int $product_id){
        $product = wc_get_product($product_id);
        $image_id  = $product->get_image_id();
        $image_url = wp_get_attachment_image_url($image_id, 'full');
        global $wpdb;
        $table_name = $wpdb->prefix.self::COVERS_LINES_TABLE; // Replace with your actual table name if different
        $sql = "UPDATE $table_name SET url_target = '".$image_url."' WHERE id = $line_id";
        $wpdb->query($sql);
    }

    public function get_product_featured_image_html($product_id) {
        return $product_id;
        // Check if the product ID is valid and a product exists
        if (!$product_id || !function_exists('wc_get_product')) {
            return 'Product ID not found.';
        }

        $product = wc_get_product($product_id);

        if (!$product) {
            return 'Product not found.';
        }

        // Get the ID of the featured image
        $image_id  = $product->get_image_id();

        // If there's no featured image, return a placeholder or a message
        if (!$image_id) {
            return 'No featured image available.';
        }

        // Get the image URL and alt text
        $image_url = wp_get_attachment_image_url($image_id, 'full'); // You can change 'full' to any other image size
        $image_alt = get_post_meta($image_id, '_wp_attachment_image_alt', true);

        // Generate and return the HTML for the image
        $html = '<a href="post.php?post='.$image_id.'&action=edit" target="_blank">';
        $html .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" style="max-width:50px; height:auto;" />';
        $html .= '</a>';
        return $html;
    }

}