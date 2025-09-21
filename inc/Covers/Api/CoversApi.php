<?php

namespace Inc\Covers\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Inc\Biblio\Api\BiblioApi;
use Psr\Http\Message\ResponseInterface;

class CoversApi {

	private $dilveSettings;
	private $cegalSettings;
	private $dilve_url_host;
  	private $dilve_url_path;
  	private $dilve_url_user;
  	private $dilve_url_pass;
	private $cegal_url_host;
  	private $cegal_url_path;
  	private $cegal_url_user;
  	private $cegal_url_pass;
	private $biblioApi;

	public function __construct(){
		$this->dilveSettings = get_option('covers_settings');
		$this->dilve_url_host = "www.dilve.es";
		$this->dilve_url_path = "/dilve/dilve";
		$this->dilve_url_user = $this->dilveSettings['dilve_user'];
    	$this->dilve_url_pass = isset($this->dilveSettings['dilve_pass']) ? $this->dilveSettings['dilve_pass'] : '';
		$this->cegalSettings = get_option('covers_settings');
		$this->cegal_url_host = "www.cegalenred.com/";
		$this->cegal_url_path = "peticiones";
		$this->cegal_url_user = $this->cegalSettings['cegal_user'];
    	$this->cegal_url_pass = isset($this->cegalSettings['cegal_pass']) ? $this->cegalSettings['cegal_pass'] : '';
		$this->biblioApi = new BiblioApi;
	}

	public function get_query ($type, $isbn) {
		if ($type == 'cegal') {
			return 'https://'
					.$this->cegal_url_host
					.$this->cegal_url_path
					.'/fichalibro.xml.php?USUARIO='
					.$this->cegal_url_user.'&CLAVE='
					.$this->cegal_url_pass.'&ISBN='
					.$isbn.'&TIPOFICHA=C';
		} 
		return 'http://'
			.$this->dilve_url_host
			.$this->dilve_url_path.
			'/getRecordsX.do?user='.
			$this->dilve_url_user.
			'&password='.$this->dilve_url_pass.
			'&identifier='.$isbn;
	}

	/**
	* Function CoversApi::search
	*
	* @param string $isbn
	*   ISBN code to search
	* @return hash
	*   hash data of book
	*/
	public function search_dilve( string $isbn ) {
		$query  = $this->get_query( 'dilve', $isbn);
		# Get xml in ONIX version 2.1
		$query .= '&metadataformat=ONIX&version=2.1';
		# Get xml in CEGAL version 3
		#$query .= '&metadataformat=CEGAL&version=3&formatdetail=C';
		# By default responses are UTF-8 encoded, but force it
		$query .= '&encoding=UTF-8';

		$response = wp_remote_get($query);

		if ( is_wp_error( $response ) ) {
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Error: " . $response->get_error_message() , 'covers');
			return false;  // In case of error return immediately.
		 } else {
			$body = wp_remote_retrieve_body( $response );  // Get the body of the response
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			   $xml = simplexml_load_string($body);
			   // Your code here to handle the $xml object
			} else {
			   return false;
			}
		 }

		if( $xml->ONIXMessage->Product != NULL ) {
			$xml_book = $xml->ONIXMessage->Product[0];
			$book = [];
			if ( $xml_book ) {
				$book['isbn'] = $isbn;//(string)$xml_book->RecordReference;
				$book['ean'] = (string) $xml_book->RecordReference;
				$book['date'] = (int) $xml_book->PublicationDate;
				$book['year'] = substr($book['date'],0, 4);

				#Get Price
				foreach($xml_book->SupplyDetail->Price as $price) {
					$book['price'] = (float)$price->PriceAmount;
					$book['price'] = str_replace('.', '', number_format($book['price'], 2));
				}
				# Get title
				foreach($xml_book->Title as $title) {
					if ($title->TitleType == "01") {
						$book["title"] = (string)$title->TitleText;
						if ($title->Subtitle) {
							$book["subtitle"] = (string)$title->Subtitle;
						}
					}
				}
				# Get Publisher.
				foreach ($xml_book->Publisher as $publisher) {
					if ($publisher->NameCodeType == 02) {
						$book['publisher'] = (string)$xml_book->Publisher->PublisherName;
					}
				}
				# Get author.
				foreach($xml_book->Contributor as $contributor) {
					if ($contributor->ContributorRole == "A01") {
						$author_name = (string) $contributor->PersonNameInverted;
						$author_description = (string) $contributor->BiographicalNote;
						if ($author_description) {
							$book["author"][] = ['name' => $author_name,
												'description' => $author_description];
						} else {
							$book["author"][] = ['name' => $author_name];
						}
					}
				}
				# Get measurements.
				foreach($xml_book->Measure as $measure) {
					$book[match ($measure->MeasureTypeCode) {
						"01" => "length",
						"02" => "width",
						"08" => "weight",
						default => null // Handle default case if needed
					}] = [
						'unit' => (string)$measure->MeasureUnitCode,
						'value' => (string)$measure->Measurement
					];
				}
				# Get number of pages.
				if($xml_book->NumberOfPages) {
					$book["pages"] = (string)$xml_book->NumberOfPages;
				}
				# Get descriptions.
				/* foreach($xml_book->OtherText as $description) {
					// Assuming $description->TextTypeCode contains the code to match
					$result = match ($description->TextTypeCode) {
						"01", "03", "05", "07", "31" => $book["description"] = nl2br((string) $description->Text),
						"09" => $book["promoting_description"] = nl2br((string) $description->Text),
						"12" => $book["short_description"] = nl2br((string) $description->Text),
						"13" => isset($book['author']) && count($book['author']) == 1 ?
								$book["author"][0]["description"] = nl2br((string) $description->Text) : null,
						"23" => $book["preview_url"] = $this->get_dilve_file_url((string) $description->TextLink, $isbn),
						default => error_log("\n-----------------------> Tipo de texto no definido (" . $description->TextTypeCode . ") para el libro con ISBN " . $isbn . "\n\n")
					};
				} */

				// GET SERIES TITLE
				if($xml_book->Series) {
					foreach($xml_book->Series as $series) {
						$book["series"][] = (string)$series->Title->TitleText;
					}
				}
				
				# Get cover URL.
				foreach ( $xml_book->MediaFile as $media ) {
					// Assuming $media->MediaFileTypeCode contains the code to match.
					$mediaFileTypeCode = (string) $media->MediaFileTypeCode;
					$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Converted Media File Type Code: " . $mediaFileTypeCode.PHP_EOL, 'covers');
					switch($mediaFileTypeCode) {
						case "03":
						case "04":
						case "05":
						case "06":
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Recogido imagen del libro: " . $media->MediaFileLink.PHP_EOL, 'covers');
							if ( !isset($book["cover_url"]) || $media->MediaFileLinkTypeCode == "06" ) {
								$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Adding cover_url " .PHP_EOL, 'covers');
								$book["cover_url"] = $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
							}
						break;
						case "07": break; // Nothing happens for case "07", equivalent to an empty break in switch.
						case "08":
							$book["image_author_url"] = (string) $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__," Recogido imagen del autor: " . $book["image_author_url"], 'covers');
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Formato: " . $media->MediaFileFormatCode, 'covers');
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Tipo de Enlace: " . $media->MediaFileLinkTypeCode, 'covers');
						break;
						case "17" :
							$book["image_publisher_url"] = (string) $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Recogido logo de editorial: " . $book["image_publisher_url"], 'covers');
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Formato: " . $media->MediaFileFormatCode, 'covers');
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Tipo de Enlace: " . $media->MediaFileLinkTypeCode, 'covers');
						case "17" :
							$book["image_publisher_url"] = (string) $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Recogido logo de editorial: " . $book["image_publisher_url"], 'covers');
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Formato: " . $media->MediaFileFormatCode, 'covers');
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Tipo de Enlace: " . $media->MediaFileLinkTypeCode, 'covers');
						break;
						case "51" :
							$book["preview_media_url"] = $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn); // Uncommented and corrected the variable name
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__," Recogido fichero de preview: " . $book["preview_media_url"], 'covers');
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__," Formato: " . $media->MediaFileFormatCode, 'covers');
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__," Tipo de Enlace: " . $media->MediaFileLinkTypeCode, 'covers');
						break;
						default:
							// Uncomment these lines if you want to execute them in the default case
							$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__," Tipo de medio no definido (" . $media->MediaFileTypeCode . ") para el libro con ISBN " . $isbn . "\n\n", 'covers');
					}
				}
			}
		} else {
			$book = (string)$xml->error->text;
		}
		return $book;
  	}

	/**
	 * Function CoversSearch::get_dilve_file_url
	 *
	 * @param string $filename
	 *   local or remote filename
	 * @param string $isbn
	 *   ISBN code to search
	 * @return string
	 *   Full URL of requested resource
	 */
  	private function get_dilve_file_url( string $filename, string $isbn): string {
    	# If URL is a covers reference, complete full request
		if (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0) {
			$url = $filename;
		} else {
			$url  = 'http://'.$this->dilve_url_host.$this->dilve_url_path.
					'/getResourceX.do?user='.$this->dilve_url_user.
					'&password='.$this->dilve_url_pass;
			$url .= '&identifier='.$isbn.
					'&resource='.urlencode($filename);
		}
    	return $url;
  	}

	/**
	 * fetch_cover
	 *
	 * @param  string $url
	 * @param  string $isbn
	 * @param  string $type
	 * @return mixed
	 */
	public function fetch_cover( string $url, string $isbn, string $type ): mixed {
		$query = (string) $this->get_query($type, $isbn);
		$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Fetch cover - URL: " . $url . PHP_EOL, 'covers');
		$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Fetch cover - Query: " . $query . PHP_EOL, 'covers');
		if ( $type == 'dilve' ) {
			$client = new Client(['verify' => false, 'timeout' => 10.0]);
			$coversApiDbLinesManager = new CoversApiDbLinesManager;
			try {
				$response = $client->get($url);
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Response from API: " . $response->getBody() . PHP_EOL, 'covers');
				return $response->getBody();
			} catch( ConnectException $connectException ) {
				$error = ['message'=> $connectException->getMessage()];
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Connection exception: " . $connectException->getMessage() , 'covers');
				$coversApiDbLinesManager->setError( $isbn, $error['message'] );
				return false;
			} catch ( RequestException $requestException ) {
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Request exception: " . $requestException->getMessage() , 'covers');
				if ($requestException->getResponse() instanceof ResponseInterface) {
					$error['statusCode'] = $requestException->getResponse()->getStatusCode();
					if ( $error['statusCode'] === 404 ) {
						$error['message'] = 'Error: Resource not found';
					} else {
						// Handle other client errors
						$error['message'] = 'Error: Client error - ' . $error['statusCode'];
					}
				} else {
					// Handle other exceptions
					$error['message'] = 'Error: ' . $requestException->getMessage();
				}
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,$error['message'], 'covers');
				$coversApiDbLinesManager->setError( $isbn, $error['message'] );
				return false;
			} catch (\Exception $exception) {
				$error['message'] = 'Error: ' . $exception->getMessage();
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $error['message'], 'covers');
				$coversApiDbLinesManager->setError( $isbn, $error['message'] );
				return false;
			}
		} else {
			$coversApiDbLinesManager = new CoversApiDbLinesManager;
			$response = wp_remote_get($query, [ 'timeout' => 140 ]);
			// Check if the response is an error
			if (is_wp_error($response)) {
				// Handle error
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Something went wrong: " . $response->get_error_message() . PHP_EOL, 'covers');
				return false;
			}
			$body = (string) wp_remote_retrieve_body($response);
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Response from API: " . $body . PHP_EOL, 'covers');
			$xmlString = (string) wp_remote_retrieve_body( $response );
			$xmlString = (string) preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]+/', '', $xmlString);
			$xml = simplexml_load_string( $xmlString );
			// Additional checks to ensure the XML and expected fields are present.
			if ( !$xml || empty( $xml->LIBRO->PORTADA->IMAGEN_PORTADA ) || empty( $xml->LIBRO->PORTADA ) ) {
				// Handle error - log or return false
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, " Error: XML or expected fields not found." . PHP_EOL, 'covers');
				return false;
			}
			return [
				'imagedata' => base64_decode( (string) $xml->LIBRO->PORTADA->IMAGEN_PORTADA ),
				'image' => (string) $xml->LIBRO->PORTADA->IMAGEN_PORTADA,
				'format' => (string) $xml->LIBRO->PORTADA->FORMATO_PORTADA
			];
		}
	}

	/**
	 * Checks if the cover exists and if it does returns the file object.
	 * It it doesn't exists downloads it and creates the object
	 *
	 * @param string $url
	 * @param string $filename
	 * @param string $mimetype
	 * @param bool $force
	 * @return mixed
	 */
	public function create_cover(
		string $url,
		string $filename,
		string $mimetype = 'image/jpeg',
		bool $force = FALSE,
		string $type = 'dilve' ): mixed {
			$isbn = (string) explode('.', $filename)[0];
			$data = $this->fetch_cover($url, $isbn, $type);
			$data = ($type == "cegal") ? $data['imagedata'] : $data;
			//$data = $this->search_dilve($isbn);
			if ( !$data ){
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Data from fetch_cover does not exist.".PHP_EOL , 'covers');
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $isbn. ' :Data from fetch_cover does not exist.'.PHP_EOL , 'covers');
				return false;
			}

			//Primero intentamos cargar la imagen de la base de datos
			$filepath = (string) sprintf("%s/portadas/%s", wp_upload_dir()['basedir'], $filename);
			// First, check if the image exists in the database
			$coversApiDbManager = new CoversApiDbManager;
			$existing_file = $coversApiDbManager->isAttachment( $filename );
			//Si existe comprobamos que efectivamente el archivo está. Si no lo creamos
			if ($existing_file && file_exists($filepath) && !$force) {
				$file_id = $existing_file[0]->ID;
				wp_update_post([
					'ID' => $existing_file[0]->ID,
					'post_modified' => current_time('mysql'),
					'post_modified_gmt' => get_gmt_from_date( current_time('mysql') ),
					'guid' => preg_replace( '/\/\d{4}\/\d{1,2}\/portadas\//', '/portadas/', $existing_file[0]->guuid ),
				]);
				$file_id = $existing_file[0]->ID;
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $isbn. ': File: '. $file_id.' already exists.'.PHP_EOL , 'covers');
			} else {
				$file_id = $coversApiDbManager->attachFile( $filepath, $data, $filename );
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "THE FILE HAS BEEN ATTACHED: " . $file_id . PHP_EOL, 'covers');
			}
			return ( $file_id > 0 ) ? get_post( $file_id ): false;
  	}

	public function scanProducts($log_id, $batch_size = -1, $offset = 0, $type = 'dilve') {
		global $wpdb;
		$coversApiDbManager = new CoversApiDbManager;
        $coversApiDbLogManager = new CoversApiDbLogManager;
        $coversApiDbLinesManager = new CoversApiDbLinesManager;
		$products = (array) $coversApiDbManager->getProductsWithoutCover();
		$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Number of scanned products: ' . count($products), 'covers');
		if(empty($products)) return false;
		if ( $batch_size > 0 ) {
			$products = (array) array_slice( $products, $offset, $batch_size );
		}
		$eans = [];
		$hasMore = (bool) !empty($products);
		$totalLines = (int) $coversApiDbManager->countAllProducts();
		$progress = 0;
		$index = 0;
		$index = 0;
        foreach( $products as $product ) { 
			//if ( $index > 100 ) break;
			// Get ean number from each product;
			$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Scanned product: ' . var_export($product, true), 'covers');
			if ( $product['ID'] == null ) {
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Scan Products - Covers Scan Products] PRODUCT ID IS NULL".PHP_EOL, 'covers');
				continue;
			}
            $ean = get_post_meta( $product['ID'], '_ean', true );
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'EAN: '. $ean , 'covers');
			$url = (string) $this->get_query($type, $ean );

			if ( $coversApiDbManager->hasAttachment( $product['ID'] ) ) {
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Scan Products - This product with EAN: '. $ean . ' has already a cover.'.PHP_EOL , 'covers');
				continue;
			}
			if ($this->validateEAN($ean) == false) continue;

			$filepath = (string) sprintf("%s/portadas/%s", wp_upload_dir()['basedir'], $ean.'.jpg');
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, " Scan Products - FILEPATH: " . $filepath.PHP_EOL , 'covers');
			$line_id = $coversApiDbLinesManager->insertLinesData($log_id, $ean, $filepath, $type);

			if ( $type == 'dilve' && $ean != '' ) {

				$filepath = (string) sprintf("%s/portadas/%s", wp_upload_dir()['basedir'], $ean.'.jpg');
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, " Scan Products - FILEPATH: " . $filepath.PHP_EOL , 'covers');
				$line_id = $coversApiDbLinesManager->insertLinesData($log_id, $ean, $filepath, $type);
            	$book = $this->search_dilve($ean);
				if ( $book && is_array($book) && isset($book['cover_url'] ) ) {
					if ($coversApiDbManager->hasAttachment( $product['ID'] ) ) {
						$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Scan Products - This product has already a cover: '.$ean.PHP_EOL, 'covers');
						continue;
					}
					
					$coversApiDbLinesManager->set_url_origin($line_id, $book['cover_url']);
					$coversApiDbLinesManager->setBook($product['post_title'], $product['ID'], $line_id);
					
					if ( $cover_post = $this->create_cover( $book['cover_url'], $ean.'.jpg', 'image/jpeg', FALSE, $type ) ) {
						$coversApiDbManager->set_featured_image_for_product($cover_post->ID, $ean);
						$coversApiDbLinesManager->set_url_target($line_id, $product['ID']);
						$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "The coverpost was properly created.".PHP_EOL, 'covers');
					} else {
						$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"The coverpost was not properly created".PHP_EOL, 'covers');
					}
					$coversApiDbManager->set_covers_url($ean, $book['cover_url']);
				}
			} else {
				if ($file = $this->create_cover( $url, $ean.'.jpg', 'image/jpeg', FALSE, $type ) ) {
					$coversApiDbLinesManager->set_url_origin($line_id, $url);
					$coversApiDbManager->set_featured_image_for_product( $file->ID, $ean );
					$coversApiDbLinesManager->setBook($product['post_title'], $product['ID'], $line_id);
					$coversApiDbLinesManager->set_url_target($line_id, $product['ID']);
					$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"The coverpost was properly created for product: ".$product['post_title'].PHP_EOL, 'covers');
				} else {
					$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"The coverpost was not properly created".PHP_EOL, 'covers');
				}
				$coversApiDbManager->set_covers_url($ean, $url);
			}
			$coversApiDbLogManager->setLogStatus($log_id, 'processing');
			$response[] = [ 'id' => $product['ID'] ];
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Offset now: ". $offset * ($index + 1).PHP_EOL , 'covers');
			$progress = ( $offset * ($index + 1) / $totalLines ) * 100;
			$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"Progress now: ". $progress.PHP_EOL , 'covers');
			array_push($eans, $ean);
			$index++;
        }
		$response['hasMore'] = $hasMore;
		$response['eans'] = $eans;
		$response['message'] = "[BIBLIO - Covers Api] " . $batch_size . " books have been processed.";
		$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, $batch_size . " books have been processed.".PHP_EOL , 'covers');
		$response['progress'] = number_format($progress, 2)." %";
		if ( $hasMore == false ) {
			$coversApiDbLogManager->setLogStatus($log_id, 'processed');
			$coversApiDbLogManager->set_processed_products($log_id, $offset + $index);
		}
        return json_encode( $response );
    }

	function validateEAN($ean) {
		// Check if the EAN is a valid length and contains only digits
		if (strlen($ean) != 13 || !ctype_digit($ean)) {
			return false;
		}

		// Split the EAN into its individual digits
		$digits = str_split($ean);

		// Calculate the checksum
		$sum = 0;
		foreach ($digits as $position => $digit) {
			if ($position < 12) { // Exclude the last digit (check digit) from calculation
				if ($position % 2 == 0) { // Even-positioned digits (considering the first digit as position 0)
					$sum += $digit; // Add directly
				} else { // Odd-positioned digits
					$sum += 3 * $digit; // Multiply by 3 and add
				}
			}
		}

		// Calculate the check digit and compare with the last digit of the EAN
		$checkDigit = (10 - ($sum % 10)) % 10;

		// Return true if the check digit matches the last digit, false otherwise
		return $checkDigit == $digits[12];
	}

	public function attach_local_missing_covers(){
		$coversApiDbManager = new CoversApiDbManager;
		// Get all products with EAN meta key that do not have a cover image
		$args = array(
			'post_type' => 'product',
			'meta_query' => array(
				array(
					'key' => '_ean',
					'compare' => 'EXISTS',
				),
				array(
					'key' => '_thumbnail_id',
					'compare' => 'NOT EXISTS',
				),
			),
			'posts_per_page' => -1, // Get all products
		);
		$products = get_posts($args);
		foreach ($products as $product) {
			$ean = get_post_meta($product->ID, '_ean', true);
			// Check if the $ean. '.jpg' file exists in a given folder of the uploads directory.
			$uploads = wp_upload_dir();
			$uploads_dir = $uploads['basedir'] . '/img/';  
			$subfolder = substr($ean, 0, 7);
        	$filename_jpg = $ean . '.jpg';
        	$filename_JPG = $ean . '.JPG';

        	$filepath_jpg = $uploads_dir . $subfolder . '/' . $filename_jpg;
        	$filepath_JPG = $uploads_dir . $subfolder . '/' . $filename_JPG;
			// If the file exists, set it as the featured image for the product.
			if (file_exists($filepath_jpg)) {
            	$filepath = $filepath_jpg;
            	$filename = $filename_jpg;
			} elseif (file_exists($filepath_JPG)) {
				$filepath = $filepath_JPG;
				$filename = $filename_JPG;
			} else {
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "File does not exist: " . $filepath_jpg . " or " . $filepath_JPG . PHP_EOL, 'covers');
				continue;
			}
			// Check if the file is an attachment in the media library
			$attachment = $coversApiDbManager->isAttachment($filename);
			if (!$attachment) {
				// If the file is not an attachment, create it
				$file_id = $this->create_cover(
					$uploads['baseurl'] . '/img/' . $subfolder . '/' . $filename,
					$ean . '.jpg',
					'image/jpeg',
					false,
					'dilve'
				);
				if ($file_id) {
					// If the file was created successfully, set it as the featured image for the product
					$coversApiDbManager->set_featured_image_for_product($file_id->ID, $ean);
					$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Featured image set for product ID: " . $product->ID . " with EAN: " . $ean . PHP_EOL, 'covers');
				} else {
					$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Failed to create attachment for product ID: " . $product->ID . " with EAN: " . $ean . PHP_EOL, 'covers');
				}
			} else {
				// If the file is already an attachment, set it as the featured image for the product
				$coversApiDbManager->set_featured_image_for_product($attachment->ID, $ean);
				$this->biblioApi->debug_log(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "Featured image already exists for product ID: " . $product->ID . " with EAN: " . $ean . PHP_EOL, 'covers');
			}
		}
	}
}