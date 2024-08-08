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
		$query  = $this->get_query( 'dilve', $isbn);
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
				# Get cover URL.
				foreach ( $xml_book->MediaFile as $media ) {
					// Assuming $media->MediaFileTypeCode contains the code to match.
					$mediaFileTypeCode = (string) $media->MediaFileTypeCode;
					error_log("[BIBLIO - Covers Api] Converted Media File Type Code: " . $mediaFileTypeCode);
					switch($mediaFileTypeCode) {
						case "03":
						case "04":
						case "05":
						case "06":
							error_log( "[BIBLIO - Covers Api] INSIDE OF MATCH FUNCTION. " . PHP_EOL );
							if ( !isset($book["cover_url"]) || $media->MediaFileLinkTypeCode == "06" ) {
								error_log('[BIBLIO - Covers Api] Adding cover_url ' . PHP_EOL);
								$book["cover_url"] = $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
							}
						break;
						case "07": break; // Nothing happens for case "07", equivalent to an empty break in switch.
						case "08":
							$book["image_author_url"] = (string) $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
							error_log("[BIBLIO - Covers Api] \n---> Recogido imagen del autor: " . $book["image_author_url"]);
							error_log("[BIBLIO - Covers Api] \n---> Formato: " . $media->MediaFileFormatCode);
							error_log("[BIBLIO - Covers Api] \n---> Tipo de Enlace: " . $media->MediaFileLinkTypeCode);
						break;
						case "17" :
							$book["image_publisher_url"] = (string) $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
							error_log("[BIBLIO - Covers Api] \n---> Recogido logo de editorial: " . $book["image_publisher_url"]);
							error_log("[BIBLIO - Covers Api] \n---> Formato: " . $media->MediaFileFormatCode);
							error_log("[BIBLIO - Covers Api] \n---> Tipo de Enlace: " . $media->MediaFileLinkTypeCode);
						break;
						case "51" :
							$book["preview_media_url"] = $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn); // Uncommented and corrected the variable name
							error_log("[BIBLIO - Covers Api] \n---> Recogido fichero de preview: " . $book["preview_media_url"]);
							error_log("[BIBLIO - Covers Api] \n---> Formato: " . $media->MediaFileFormatCode);
							error_log("[BIBLIO - Covers Api] \n---> Tipo de Enlace: " . $media->MediaFileLinkTypeCode);
						break;
						default:
							// Uncomment these lines if you want to execute them in the default case
							error_log( "[BIBLIO - Covers Api] ".var_export( $media, true) );
							error_log("[BIBLIO - Covers Api] \n-> Tipo de medio no definido (" . $media->MediaFileTypeCode . ") para el libro con ISBN " . $isbn . "\n\n");
					}
				}
				error_log( '[BIBLIO - Covers Api] BOOK: ' . var_export( $book, true ) );
			}
		} else {
			$book = (string)$xml->error->text;
		}
		error_log("[BIBLIO - Covers Api] ".var_export($book, true));
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
		error_log('[BIBLIO - Covers Api] url: ' . var_export($url, true));
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
		if ( $type == 'dilve' ) {
			$client = new Client(['verify' => false, 'timeout' => 10.0]);
			$coversApiDbLinesManager = new CoversApiDbLinesManager;
			try {
				$response = $client->get($url);
			} catch( ConnectException $connectException ) {
				$error = ['message'=> $connectException->getMessage()];
				error_log( '[BIBLIO - Covers Api] Connection exception: ' . $connectException->getMessage() );
				$coversApiDbLinesManager->setError( $isbn, $error['message'] );
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
					$error['message'] = 'Error: ' . $requestException->getMessage();
				}
				error_log("[BIBLIO - Covers Api] ".$error['message']);
				$coversApiDbLinesManager->setError( $isbn, $error['message'] );
				return false;
			} catch (\Exception $exception) {
				$error['message'] = 'Error: ' . $exception->getMessage();
				error_log("[BIBLIO - Covers Api] ".$error['message']);
				$coversApiDbLinesManager->setError( $isbn, $error['message'] );
				return false;
			}
			if ( $response->getStatusCode() == 200 ) {
				return $response->getBody();
			} else {
				return false;
			}
		} else {
			$coversApiDbLinesManager = new CoversApiDbLinesManager;
			$response = wp_remote_get($query, [ 'timeout' => 140 ]);
			// Check if the response is an error
			if (is_wp_error($response)) {
				// Handle error
				error_log("[BIBLIO - Covers Api] Something went wrong: " . $response->get_error_message());
				return false;
			}
			$body = (string) wp_remote_retrieve_body($response);
			error_log("[BIBLIO - Covers Api] Response from API: " . $body);
			$xmlString = (string) wp_remote_retrieve_body( $response );
			$xmlString = (string) preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]+/', '', $xmlString);
			$xml = simplexml_load_string( $xmlString );
			// Additional checks to ensure the XML and expected fields are present.
			if ( !$xml || empty( $xml->LIBRO->PORTADA->IMAGEN_PORTADA ) || empty( $xml->LIBRO->PORTADA ) ) {
				// Handle error - log or return false
				error_log( '[BIBLIO - Covers Api] Error: XML or expected fields not found.' );
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
			error_log( "[BIBLIO - Covers Api] DATA FROM FETCH COVER: " . var_export( $data, true ) );
			$data = ($type == "cegal") ? $data['imagedata'] : $data;
			//$data = $this->search_dilve($isbn);
			error_log( "[BIBLIO - Covers Api] TYPE: " . var_export( $type, true ) );
			error_log( "[BIBLIO - Covers Api] DATA FROM FETCH COVER: " . var_export( $data, true ) );
			if ( !$data ){
				error_log( '[BIBLIO - Covers Api] Data from fetch_cover does not exist.' );
				error_log( '[BIBLIO - Covers Api] '.$isbn. ' :Data from fetch_cover does not exist.' );
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
				error_log( '[BIBLIO - Covers Api] '. $isbn. ': File: '. $file_id.' already exists.' );
			} else {
				$file_id = $coversApiDbManager->attachFile( $filepath, $data, $filename );
				error_log( "[BIBLIO - Covers Api] THE FILE HAS BEEN ATTACHED: " . var_export($file_id , true ) );
			}
			return ( $file_id > 0 ) ? get_post( $file_id ): false;
  	}


	public function scanProducts($log_id, $batch_size = -1, $offset = 0, $type = 'dilve') {
		global $wpdb;
		$coversApiDbManager = new CoversApiDbManager;
        $coversApiDbLogManager = new CoversApiDbLogManager;
        $coversApiDbLinesManager = new CoversApiDbLinesManager;
		$products = (array) $coversApiDbManager->getProductsWithoutCover();
		
		if(empty($products)) return false;
		if ( $batch_size > 0 ) {
			$products = (array) array_slice( $products, $offset, $batch_size );
		}
		$eans = [];
		$hasMore = (bool) !empty($products);
		$totalLines = (int) $coversApiDbManager->countAllProducts();
		$progress = 0;
		$index = 0;
        foreach( $products as $product ) {
			//if ( $index > 100 ) break;
			// Get ean number from each product;
			error_log('[BIBLIO - Covers Scan Products]'.var_export($product, true));
			if($product['ID'] == null ) {
				error_log('[BIBLIO - Covers Scan Products] PRODUCT ID IS NULL', true);
				continue;
			}
			error_log('[BIBLIO - Covers Scan Products] POSTMETA: '.var_export(get_post_meta($product['ID']), true));
            $ean = get_post_meta( $product['ID'], '_ean', true );
			error_log('[BIBLIO - Covers Scan Products] EAN: '. $ean );
			$url = (string) $this->get_query($type, $ean );

			if ( $coversApiDbManager->hasAttachment( $product['ID'] ) ) {
				error_log('[BIBLIO - Covers Scan Products]This product with EAN: '. $ean . ' has already a cover.' );
				continue;
			}
			if ($this->validateEAN($ean) == false) continue;

			$filepath = (string) sprintf("%s/portadas/%s", wp_upload_dir()['basedir'], $ean.'.jpg');
			error_log( "[BIBLIO - Covers Api] FILEPATH: " . $filepath );
			$line_id = $coversApiDbLinesManager->insertLinesData($log_id, $ean, $filepath, $type);

			if ( $type == 'dilve' && $ean != '' ) {
            	$book = $this->search_dilve($ean);
				error_log("[BIBLIO - Covers Api] BOOK: " . var_export( $book, true ) );
				if( $book && is_array($book) && isset($book['cover_url'] ) ) {
					if ($coversApiDbManager->hasAttachment( $product['ID'] ) ) {
						error_log('[BIBLIO - Covers Api] This product has already a cover: '.$ean);
						continue;
					}
					$coversApiDbLinesManager->set_url_origin($line_id, $book['cover_url']);
					$coversApiDbLinesManager->setBook($product['post_title'], $product['ID'], $line_id);
					if ( $cover_post = $this->create_cover( $book['cover_url'], $ean.'.jpg', 'image/jpeg', FALSE, $type ) ) {
						$coversApiDbManager->set_featured_image_for_product($cover_post->ID, $ean);
						$coversApiDbLinesManager->set_url_target($line_id, $product['ID']);
						error_log('[BIBLIO - Covers Api] The coverpost was properly created.');
					} else {
						error_log(' [BIBLIO - Covers Api]The coverpost was not properly created');
					}
					$coversApiDbManager->set_covers_url($ean, $book['cover_url']);
				}
			} else {
				if ($file = $this->create_cover( $url, $ean.'.jpg', 'image/jpeg', FALSE, $type ) ) {
					$coversApiDbLinesManager->set_url_origin($line_id, $url);
					$coversApiDbManager->set_featured_image_for_product( $file->ID, $ean );
					$coversApiDbLinesManager->setBook($product['post_title'], $product['ID'], $line_id);
					$coversApiDbLinesManager->set_url_target($line_id, $product['ID']);
					error_log('[BIBLIO - Covers Api] The coverpost was properly created for product: '.$product['post_title']);
				} else {
					error_log('[BIBLIO - Covers Api] The coverpost was not properly created');
				}

				$coversApiDbManager->set_covers_url($ean, $url);
			}
			$coversApiDbLogManager->setLogStatus($log_id, 'processed');
			$response[] = [ 'id' => $product['ID'] ];
			error_log('[BIBLIO - Covers Api] Offset now: '. $offset );
			$progress = ( $offset / $totalLines ) * 100;
			error_log('[BIBLIO - Covers Api]Progress now: '. $progress );
			array_push($eans, $ean);
			$index++;
        }
		$response['hasMore'] = $hasMore;
		$response['eans'] = $eans;
		$response['message'] = "[BIBLIO - Covers Api] " . $batch_size . " books have been processed: ";
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