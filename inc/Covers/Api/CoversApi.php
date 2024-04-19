<?php

namespace Inc\Covers\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
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
		} else {
			return 'http://'
			.$this->dilve_url_host
			.$this->dilve_url_path.
			'/getRecordsX.do?user='.
			$this->dilve_url_user.
			'&password='.$this->dilve_url_pass.
			'&identifier='.$isbn;
		}
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
		$query  = $this->get_query('dilve',$isbn);
		# Get xml in ONIX version 2.1
		$query .= '&metadataformat=ONIX&version=2.1';
		# Get xml in CEGAL version 3
		#$query .= '&metadataformat=CEGAL&version=3&formatdetail=C';
		# By default responses are UTF-8 encoded, but force it
		$query .= '&encoding=UTF-8';

		$response = wp_remote_get($query);

		if ( is_wp_error( $response ) ) {
			return;  // In case of error return immediately.
		 } else {
			$body = wp_remote_retrieve_body( $response );  // Get the body of the response
			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
			   $xml = simplexml_load_string($body);
			   // Your code here to handle the $xml object
			} else {
			   return;
			}
		 }

		if($xml->ONIXMessage->Product != NULL ) {
			$xml_book = $xml->ONIXMessage->Product[0];
			$book = [];
			if ($xml_book) {

			//drupal_set_message(dprint_r($xml_book, 1));

			$book['isbn'] = $isbn;//(string)$xml_book->RecordReference;
			$book['ean'] = (string)$xml_book->RecordReference;
			$book['date'] = (int)$xml_book->PublicationDate;
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

			//Get Publisher
			foreach ($xml_book->Publisher as $publisher) {
				if ($publisher->NameCodeType == 02) {
					$book['publisher'] = (string)$xml_book->Publisher->PublisherName;
				}
			}

			# Get author
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
			# Get measurements
			foreach($xml_book->Measure as $measure) {
				switch ($measure->MeasureTypeCode) {
				case "01":
					$book["length"] = array('unit' => (string)$measure->MeasureUnitCode, 'value' => (string)$measure->Measurement);
					break;
				case "02":
					$book["width"] = array('unit' => (string)$measure->MeasureUnitCode, 'value' => (string)$measure->Measurement);
					break;
				case "08":
					$book["weight"] = array('unit' => (string)$measure->MeasureUnitCode, 'value' => (string)$measure->Measurement);
					break;
				}
			}
			# Get number of pages
			if($xml_book->NumberOfPages) {
				$book["pages"] = (string)$xml_book->NumberOfPages;
			}
				# Get descriptions
				foreach($xml_book->OtherText as $description) {
					switch ($description->TextTypeCode) {
					case "01":
					case "03":
					case "05":
					case "07":
					case "31":
						//Descripción general
						$book["description"] = nl2br( (string) $description->Text );
						break;
					case "09":
						$book["promoting_description"] = nl2br( (string) $description->Text );
						break;
					case "12":
						$book["short_description"] = nl2br( (string) $description->Text );
						break;
					case "13":
						if ( isset($book['author']) && count($book['author']) == 1 ) {
						$book["author"][0]["description"] = nl2br( (string) $description->Text );
						}
						break;
					case "23":
						$book["preview_url"] = $this->get_dilve_file_url((string) $description->TextLink, $isbn, 'dilve');
						#print "\n---> Recogido fichero de preview: " . $book["*preview_url"] ." --- ";
						#print_r($description);
						break;
					default:
						#print "\n-----------------------> Tipo de texto no definido (".$description->TextTypeCode.") para el libro con ISBN ".$isbn."\n\n";
					}
				}
				# Get cover URL
				foreach ($xml_book->MediaFile as $media) {
					switch ($media->MediaFileTypeCode) {
					# Covers
					case "03":
					case "04":
					case "05":
					case "06":
						# Its better covers uris
						if (!isset($book["cover_url"]) || $media->MediaFileLinkTypeCode == "06") {
						$book["cover_url"] = $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn, 'dilve');
						}
					break;
					# Cover miniature
					case "07":
						break;
					# Author image
					case "08":
						$book["image_author_url"] = $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn, 'dilve');
						#print "\n---> Recogido imagen del autor: " . $book["*image_author_url"];
						#print "\n---> Formato: " . $media->MediaFileFormatCode;
						#print "\n---> Tipo de Enlace: " . $media->MediaFileLinkTypeCode;
						break;
					# Publisher logo
					case "17":
						$book["image_publisher_url"] = $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn, 'dilve');
						#print "\n---> Recogido logo de editorial: " . $book["*image_publisher_url"];
						#print "\n---> Formato: " . $media->MediaFileFormatCode;
						#print "\n---> Tipo de Enlace: " . $media->MediaFileLinkTypeCode;
						break;
					# Preview book
					case "51";
						#$book["*preview_media_url"] = $this->::get_file_url((string) $media->MediaFileLink, $isbn);
						#print "\n---> Recogido fichero de preview: " . $book["*preview_media_url"];
						#print "\n---> Formato: " . $media->MediaFileFormatCode;
						#print "\n---> Tipo de Enlace: " . $media->MediaFileLinkTypeCode;
						#break;e
					default:
						#print_r ($media);
						#print "\n-----------------------> Tipo de medio no definido (".$media->MediaFileTypeCode.") para el libro con ISBN ".$isbn."\n\n";
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
  	private function get_dilve_file_url( string $filename, string $isbn, string $type ): string {
    	# If URL is a covers reference, complete full request
    	if ($type == 'dilve') {
			if (strpos($filename, 'http://') === 0 || strpos($filename, 'https://') === 0) {
				$url = $filename;
			} else {
				$url  = 'http://'.$this->dilve_url_host.$this->dilve_url_path.
						'/getResourceX.do?user='.$this->dilve_url_user.
						'&password='.$this->dilve_url_pass;
				$url .= '&identifier='.$isbn.
						'&resource='.urlencode($filename);
			}
		}
    	return $url;
  	}

	public function fetch_cover(string $url, string $isbn, string $type ) {
		$query = $this->get_query($type, $isbn);
		$coversApiDbLinesManager = new CoversApiDbLinesManager;
		try {
			$response = wp_remote_get($query, [ 'timeout' => 140 ]);
		} catch( ConnectException $connectException ) {
			$error = ['message'=> $connectException->getMessage()];
			error_log( 'Connection exception: ' . $connectException->getMessage() );
			return false;
		} catch ( RequestException $requestException ) {
			error_log( 'Request exception: ' . $requestException->getMessage() );
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
				$error['message'] = 'Error: ' . $e->getMessage();
			}
			error_log($error['message']);
			return false;
		} catch (\Exception $exception) {
			$error['message'] = 'Error: ' . $exception->getMessage();
			error_log($error['message']);
			return false;
		}
		if ( isset( $response->errors ) && count( $response->errors ) > 0 ) {

			$errorString = '';
			foreach($response->errors as $error) {
				$errorString .= ' ' . $error;
			}
			error_log( var_export( $errorString, true ));
			return false;
		}
		if ( $type == 'dilve' ) {
			if ( $response->getStatusCode() == 200 ) {
				return $response->getBody();
			}
		} else {
			$xmlString = wp_remote_retrieve_body( $response );
			$xmlString = preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]+/', '', $xmlString);
			$xml = simplexml_load_string( $xmlString );
			// Additional checks to ensure the XML and expected fields are present.
			if ( !$xml || empty( $xml->LIBRO->PORTADA->IMAGEN_PORTADA ) || empty( $xml->LIBRO->PORTADA ) ) {
				// Handle error - log or return false
				error_log( 'Error: XML or expected fields not found.' );
				return false;
			}
			return [
				'data' => base64_decode( (string) $xml->LIBRO->PORTADA->IMAGEN_PORTADA ),
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
		string $type = 'dilve'): mixed {

			$isbn = explode('.', $filename)[0];
			$data = $this->fetch_cover($url, $isbn, $type);
			if ( !$data ){
				error_log('Data from fetch_cover does not exist.');
				error_log( $isbn. ' :Data from fetch_cover does not exist.' );
				return false;
			}

			//Primero intentamos cargar la imagen de la base de datos
			$filepath = sprintf("%s/portadas/%s", wp_upload_dir()['basedir'], $filename);
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
				error_log( $isbn. ': File: '. $file_id.' already exists.' );
			} else {
				$file_id = $coversApiDbManager->attachFile( $filepath, $data, $filename );
			}
			return ( $file_id > 0 ) ? get_post( $file_id ): false;
  	}


	public function scanProducts($log_id, $batch_size = 0, $offset = -1, $type= 'dilve') {
		global $wpdb;
		$coversApiDbManager = new CoversApiDbManager;
        $coversApiDbLogManager = new CoversApiDbLogManager;
        $coversApiDbLinesManager = new CoversApiDbLinesManager;

		// Read all products.
		// Query for all products.
		$batch_size = (isset($_POST['batch_size']) && $_POST['batch_size'] != null) ? $_POST['batch_size'] : -1;
		$offset = (isset($_POST['offset']) && $_POST['offset'] != null) ? $_POST['offset']: 0;

		$query = $wpdb->prepare("SELECT * FROM {$wpdb->posts}
								WHERE ID NOT IN (
									SELECT post_id from {$wpdb->postmeta}
									WHERE meta_key = '_thumbnail_id'
								)
								AND post_type = 'product'
								AND post_status = 'publish'
								LIMIT %d, %d", $offset, $batch_size);
		$products = $wpdb->get_results( $query, OBJECT_K );

		$eans = [];
		$hasMore = !empty($products);
		$totalLines = $coversApiDbManager->countAllProducts();
		$progress = 0;

        foreach( $products as $product ) {
            $ean = get_post_meta( $product->get_id(), '_ean', true );
			if ($this->validateEAN($ean) == false) continue;
			$filepath = sprintf("%s/portadas/%s", wp_upload_dir()['basedir'], $ean.'.jpg');
			if ($type == 'dilve') {
            	$book = $this->search_dilve($ean);
			}

           /*  if ( $book && isset($book['cover_url'] ) ) { */
			$line_id = $coversApiDbLinesManager->insertLinesData($log_id, $ean, $filepath);
			if ( $coversApiDbManager->hasAttachment( $product->get_id() ) ) {
				$coversApiDbLinesManager->setError( $ean, 'This product has already a cover.' );
				continue;
			}
			if ($file = $this->create_cover( $ean, $type )) {
				$coversApiDbManager->set_featured_image_for_product( $file->ID, $ean );
			    $coversApiDbLinesManager->setBook($product->post_title, $product->ID, $line_id);
				if ( $type == 'dilve') {
					$coversApiDbLinesManager->set_origin_url($line_id, $book['cover_url']);
					$coversApiDbManager->set_dilve_url($ean, $book['cover_url']);
				} else {
					$url = $this->query('fichalibro.php', $ean );
					$coversApiDbManager->set_cegal_url($ean, $url);
				}
			//if ($cover_post = $this->create_cover( $book['cover_url'], $ean.'.jpg' )) {
				$coversApiDbManager->set_featured_image_for_product($cover_post->ID, $ean);
				$coversApiDbLinesManager->set_url_target($line_id, $product->get_id());
				error_log('The coverpost was properly created');
			//} else {
				//error_log('The coverpost was not properly created');
			}

            /* } */
			$coversApiDbLogManager->setLogStatus($log_id, 'processed');
			$response[] = [ 'id' => $product->get_id() ];
			error_log('Offset now: '. $offset );
			$progress = ( $offset / $totalLines ) * 100;
			error_log('Progress now: '. $progress );
			array_push($eans, $ean);
        }
		$response['hasMore'] = $hasMore;
		$response['eans'] = $eans;
		$response['message'] = $batch_size." books have been processed: ";
		$response['progress'] = number_format($progress, 2)." %";
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
}