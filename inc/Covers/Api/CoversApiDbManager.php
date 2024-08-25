<?php

namespace Inc\Covers\Api;

require_once(ABSPATH . 'wp-admin/includes/image.php');

use WP_Query;

class CoversApiDbManager {
    const COVERS_LOG_TABLE = 'covers_log';
    const COVERS_LINES_TABLE = 'covers_lines';

    static $coversLogKeys = [
		'start_date', // date
		'end_date', // date
		'status', // string waiting | enqueued | processed
		'scanned_items', // int number of lines
        'processed_items', // int number of lines
	];

    static $coversLinesKeys = [
        'log_id', // int relation oneToMany with covers_log
        'isbn',    // string
        'path',    // string
        'url_origin', // string
        'url_target', // string
        'date',    // date
        'isError', // boolean
        'error',   // string
        'attempts', // int
        'type', // int
    ];
    


    public function getProductsWithoutCover(): array {
        global $wpdb;
        // Read all products.
		// Query for all products.
		$batch_size = (int) (isset($_POST['batch_size']) && $_POST['batch_size'] != null) ? $_POST['batch_size'] : -1;
		$offset = (int) (isset($_POST['offset']) && $_POST['offset'] != null) ? $_POST['offset']: 0;
		$sql = "SELECT * FROM {$wpdb->posts}
				WHERE ID NOT IN (
									SELECT post_id from {$wpdb->postmeta}
									WHERE meta_key = '_thumbnail_id'
								)
				AND post_type = 'product'
				AND post_status = 'publish'"; 

		if ($batch_size != -1) {
			$sql .= " LIMIT %d, %d";
			$query = $wpdb->prepare($sql, $offset, $batch_size);
		} else {
			$query = $wpdb->prepare($sql);
		}
		return $wpdb->get_results( $query, ARRAY_A );

    }
    /**
     * insertFile
     *
     * @param  string $filepath
     * @param  mixed $data
     * @param  string $filename
     * @return mixed
     */
    public function attachFile( string $filepath, mixed $data, string $filename ): mixed {
        // Validate data before proceeding
        if ( empty( $data ) ) {
            error_log( "[BIBLIO - Covers Api] Data is empty. Skipping file creation.");
            return false;
        }
        try {
            file_put_contents( $filepath, $data );
            error_log('[BIBLIO - Covers Api] FILE SUCCES FULLY STORED IN THE SYSTEM at' . $filepath);
        } catch ( \Exception $exception ) {
            error_log('[BIBLIO - Covers Api] Could not create file: ' . $exception->getMessage() );
            return false;
        }
		return $this->insertAttachment( $filename, $filepath );
    }

     /**
     * hasAttachment
     * @param int $product_id
     * @return bool
     */
     public function hasAttachment(int $product_id): bool {
        // Get the EAN number from product metadata
        $ean = get_post_meta($product_id, '_ean', true);

        // Check if EAN is set
        if (empty($ean)) {
            return false;
        }

        // Construct the expected file path
        $upload_dir = wp_upload_dir();
        $expected_file_path = $upload_dir['basedir'] . '/portadas/' . $ean . '.jpg';

        // Get the ID of the product's featured image
        $thumbnail_id = get_post_thumbnail_id($product_id);

        // Check if the product has a featured image
        if (!$thumbnail_id) {
            return false;
        }

        // Get the file path of the featured image
        $thumbnail_path = get_attached_file($thumbnail_id);

        // Compare the paths
        if ($thumbnail_path === $expected_file_path) {
            return true;
        } else {
            return false;
        }
    }

    /**
  	 * set_featured_image_for_product
  	 *
  	 * @param  mixed $file_id
  	 * @param  mixed $ean
  	 * @return void
  	 */
  	public function set_featured_image_for_product( $file_id, $ean ): void {
		$args = array(
			'post_type' => 'product',
			'meta_query' => array(
				array(
					'key' => '_ean',
					'value' => $ean,
				),
			),
		);
    	$products = get_posts($args);
		foreach ($products as $product) {
			$product_id = $product->ID;
			// Check if a thumbnail is already set for the product
			if (get_post_thumbnail_id($product_id)) {
				continue; // Skip setting the featured image if already set
			}
			set_post_thumbnail($product_id, $file_id);
		}
	}

    /**
     * set_dilve_url
     *
     * @param  mixed $ean
     * @param  mixed $url
     * @return bool
     */
    public function set_covers_url(string $ean, string $url): bool {
        $args = [
			'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 1,
			'meta_query' => [
				[
					'key' => '_ean',
					'value' => $ean,
                    'compare' => '=',
                ],
            ],
        ];
    	$query = new WP_Query($args);
		if ($query->have_posts()) {
            $query->the_post();
            // Get the product object
            $product_id = get_the_ID();
            $product = wc_get_product($product_id); // Return the product ID
            if ($product && get_post_meta($product_id, '_ean', true)) {
                // Update the 'covers_url' custom field
                try {
                    update_post_meta($product_id, 'covers_url', $url);
                    return true;
                } catch (\Exception $e) {
                    error_log('[BIBLIO - Covers Api] Failed to update the covers_url custom field: '.$e->getMessage());
                    return false;
                }
            }
            return false;
        } else {
            return false; // No product found with the given EAN
        }
    }

    /**
     * countAllProducts
     *
     * @return int
     */
    public function countAllProducts(): int {
        return count( wc_get_products( [
            'status' => 'publish',
            'limit' => -1,
        ]));
	}

    /**
     * CoversApiDbManager->insertAttachment
     * Inserts the file to the file manager.
     *
     * @param  string $filename
     * @param  string $filepath
     * @return mixed
     */
    public function insertAttachment( string $filename, string $filepath ): mixed {
        $args = [
            'post_mime_type' => 'image/jpeg',
            'post_title' => 'PORTADA: '. $filename,
            'post_content' => 'PORTADA: '. $filepath . $filename,
            'post_status' => 'inherit',
            'guid' => wp_upload_dir()[ 'baseurl' ] . '/portadas/' . $filename,
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => get_gmt_from_date(current_time('mysql')),
        ];

        try {
            $attachment_id = wp_insert_attachment( $args, $filepath, 0 );
            $attachment_metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );
            wp_update_attachment_metadata( $attachment_id, $attachment_metadata );
            return $attachment_id ;
        } catch(\Exception $exception) {
            error_log( "[BIBLIO - Covers Api] Exception: ".$exception->getMessage() );
            return 0;
        }
    }

    /**
     * isAttachment
     *
     * @param  string $filename
     * @return mixed
     */
    public function isAttachment( string $filename ): mixed {
        $attachments = get_posts([
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'meta_key' => '_wp_attached_file',
			'meta_value' => 'portadas/' . $filename,
			'posts_per_page' => 1,
		]);

        return ( !empty( $attachments ) )? $attachments : false;
    }
}