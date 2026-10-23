<?php

namespace Inc\Covers\Api;

require_once(ABSPATH . 'wp-admin/includes/image.php');

use Inc\Biblio\Api\BiblioApi;
use Inc\Biblio\Api\DatabaseGuard;
use Inc\Biblio\Api\TableConstants;
use WP_Query;

class CoversApiDbManager {
    const COVERS_LOG_TABLE = TableConstants::COVERS_LOG_TABLE;
    const COVERS_LINES_TABLE = TableConstants::COVERS_LINES_TABLE;
    const COVERS_LOGGER_TABLE = TableConstants::COVERS_LOGGER_TABLE;

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
        'booktitle', // string
        'book_id', // int
        'path',    // string
        'url_origin', // string
        'url_target', // string
        'date',    // date
        'isError', // boolean
        'error',   // string
        'attempts', // int
        'type', // int
    ];
    
    private $biblioApi;

    public function __construct() {
        $this->biblioApi = new BiblioApi;
        static $meta_registered = false;
        if ( $meta_registered ) {
            return;
        }
        // register product meta so the source URL is stored and available in REST
        if ( ! did_action('init') ) {
            add_action('init', [ $this, 'register_cover_meta' ]);
        } else {
            $this->register_cover_meta();
        }
        $meta_registered = true;
    }

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
     * Published products with a non-empty _ean meta and NO _subtitle meta row.
     *
     * Explicit parameters only (unlike getProductsWithoutCover, which reads
     * $_POST). Products that already have a thumbnail ARE candidates: the
     * Dilve subtitle scan is decoupled from the cover pipeline.
     *
     * @param  int $batch_size LIMIT row count (-1 = all).
     * @param  int $offset     LIMIT offset.
     * @return array
     */
    public function getProductsWithoutSubtitle(int $batch_size = -1, int $offset = 0): array {
        global $wpdb;
        // Candidates: _ean meta row non-empty AND no _subtitle meta row at all.
		$sql = "SELECT * FROM {$wpdb->posts}
				WHERE ID IN (
								SELECT post_id from {$wpdb->postmeta}
								WHERE meta_key = '_ean'
								AND meta_value <> ''
							)
				AND ID NOT IN (
									SELECT post_id from {$wpdb->postmeta}
									WHERE meta_key = '_subtitle'
								)
				AND post_type = 'product'
				AND post_status = 'publish'";

		if ($batch_size != -1) {
			$sql .= " LIMIT %d, %d";
			$query = $wpdb->prepare($sql, $offset, $batch_size);
		} else {
			// No placeholders: prepare() without any is a wpdb doing_it_wrong.
			$query = $sql;
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
            $this->biblioApi->getLogger()->error( __CLASS__. ':'.__LINE__.' '.__FUNCTION__, "attach_file: data is empty path=" . $filepath , 'covers');
            return false;
        }
        if ( false === file_put_contents( $filepath, $data ) ) {
            $this->biblioApi->getLogger()->error( __CLASS__. ':'.__LINE__.' '.__FUNCTION__, "attach_file: could not create file path=" . $filepath , 'covers');
            return false;
        }
        $this->biblioApi->getLogger()->info( __CLASS__. ':'.__LINE__.' '.__FUNCTION__, "attach_file: path=" . $filepath . " ok" , 'covers');
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
	 * The $channel selects the log file for the lines emitted here: the
	 * covers cron uses the default 'covers'; the missing-covers cron passes
	 * 'missingcovers' so entries do not leak into the wrong channel.
	 *
  	 * @param  mixed $file_id
  	 * @param  mixed $ean
	 * @param  string $channel Log channel (covers|missingcovers).
  	 * @return void
  	 */
  	public function set_featured_image_for_product( $file_id, $ean, string $channel = 'covers' ): void {
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
		$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "set_featured_image: ean=" . $ean . " products=" . count($products) , $channel);
		foreach ($products as $product) {
			$product_id = $product->ID;

			// Check if a thumbnail is already set for the product
			if (get_post_thumbnail_id($product_id)) {
				$this->biblioApi->getLogger()->debug(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "set_featured_image: id=" . $product_id . " skipped already has thumbnail" , $channel);
				continue; // Skip setting the featured image if already set
			}
			try {
				$isset = set_post_thumbnail($product_id, $file_id);
				$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "set_featured_image: id=" . $product_id . " file_id=" . $file_id . " set=" . ( $isset ? 'yes' : 'no' ), $channel);
			} catch(\Exception $e) {
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "set_featured_image: id=" . $product_id . " file_id=" . $file_id . " error=" . $e->getMessage(), $channel);
			}
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
                    $this->biblioApi->getLogger()->error( __CLASS__. ':'.__LINE__.' '.__FUNCTION__, "set_covers_url: update failed ean=" . $ean . " error=" . $e->getMessage() , 'covers');
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
        global $wpdb;
            /* $products = wc_get_products([
                'status' => 'publish',
                'limit' => -1,
            ]);
            $this->biblioApi->debug_log(__CLASS__.':'.__LINE__.' '.__FUNCTION__, "after wc products.", 'covers');
            if (!is_array($products)) {
                $this->biblioApi->debug_log(__CLASS__.':'.__LINE__.' '.__FUNCTION__, "wc_get_products did not return an array.", 'covers');
                return 0;
            }
            $count = count($products); */
            $count = (int) DatabaseGuard::guard($wpdb->get_var(
					"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
					), __METHOD__, 'covers');
            return $count;
    }

    /**
     * CoversApiDbManager->insertAttachment
     * Inserts the file to the file manager.
     *
     * @param  string $filename
     * @param  string $filepath
     * @return mixed
     */
    public function insertAttachment( string $filename, string $filepath): mixed {
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
            $this->biblioApi->getLogger()->error( __CLASS__. ':'.__LINE__.' '.__FUNCTION__, "insert_attachment: failed filename=" . $filename . " error=" . $exception->getMessage() , 'covers');
            return false;
        }
    }

    /**
     * isAttachment
     *
     * @param  string $filename
     * @return array
     */
    public function isAttachment( string $filename ): array {
        $attachments = get_posts([
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'meta_key' => '_wp_attached_file',
			'meta_value' => 'portadas/' . $filename,
			'posts_per_page' => 1,
		]);

        return !empty($attachments) ? $attachments : [];
    }
    /**
     * Register product post meta for cover source URL.
     */
    public function register_cover_meta(): void {
        // Existing key used in the plugin: 'covers_url'
        register_post_meta(
            'product',
            'covers_url',
            [
                'show_in_rest'      => true,
                'single'            => true,
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'auth_callback'     => function() {
                    return current_user_can('edit_posts');
                },
            ]
        );
    }

    /**
     * Save cover source URL for a product id (sanitized).
     *
     * @param int $product_id
     * @param string $url
     * @return bool
     */
    public function save_cover_source_by_product_id(int $product_id, string $url): bool {
        if ($product_id <= 0 || empty($url)) {
            // Never log $url: callers pass Dilve/Cegal URLs that embed credentials.
            $this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "save_cover_source: invalid input product_id=" . $product_id . " url=" . ( empty($url) ? 'empty' : 'present' ), 'covers');
            return false;
        }
        return (bool) update_post_meta($product_id, 'covers_url', esc_url_raw($url));
    }


}