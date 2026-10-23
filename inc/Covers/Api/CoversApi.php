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
			$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "search_dilve: request failed isbn=" . $isbn . " wp_error=" . $response->get_error_message() , 'covers');
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
				# Get title (pure seam: parseOnixTitles)
				foreach ( $this->parseOnixTitles( $xml_book ) as $title_key => $title_value ) {
					$book[$title_key] = $title_value;
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
					switch($mediaFileTypeCode) {
						case "03":
						case "04":
						case "05":
						case "06":
							if ( !isset($book["cover_url"]) || $media->MediaFileLinkTypeCode == "06" ) {
								$book["cover_url"] = $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
							}
						break;
						case "07": break; // Nothing happens for case "07", equivalent to an empty break in switch.
						case "08":
							$book["image_author_url"] = (string) $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
						break;
						case "17":
							$book["image_publisher_url"] = (string) $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn);
						break;
						case "51":
							$book["preview_media_url"] = $this->get_dilve_file_url((string) $media->MediaFileLink, $isbn); // Uncommented and corrected the variable name
						break;
						default:
							$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "search_dilve: unknown media type=" . $mediaFileTypeCode . " isbn=" . $isbn, 'covers');
					}
				}
				// One info line per product: which media URLs were extracted.
				$media_kinds = array();
				if ( isset($book["cover_url"]) ) {
					$media_kinds[] = 'cover';
				}
				if ( isset($book["image_author_url"]) ) {
					$media_kinds[] = 'author_image';
				}
				if ( isset($book["image_publisher_url"]) ) {
					$media_kinds[] = 'publisher_logo';
				}
				if ( isset($book["preview_media_url"]) ) {
					$media_kinds[] = 'preview';
				}
				$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "search_dilve: isbn=" . $isbn . " media=" . ( $media_kinds ? implode(',', $media_kinds) : 'none' ), 'covers');
			}
		} else {
			$book = (string)$xml->error->text;
		}
		return $book;
  	}

	/**
	 * Extract the main title (ONIX TitleType 01) and optional subtitle from a
	 * single ONIX <Product> element.
	 *
	 * Pure seam: no WordPress functions, unit-testable via
	 * simplexml_load_string() on a fixture.
	 *
	 * @param \SimpleXMLElement $xml_book
	 *   One ONIX <Product> element.
	 * @return array
	 *   ['title' => string] and, when present, ['subtitle' => string].
	 */
	public function parseOnixTitles(\SimpleXMLElement $xml_book): array {
		$titles = [];
		foreach($xml_book->Title as $title) {
			if ($title->TitleType == "01") {
				$titles["title"] = (string)$title->TitleText;
				if ($title->Subtitle) {
					$titles["subtitle"] = (string)$title->Subtitle;
				}
			}
		}
		return $titles;
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
		// Never log $url/$query here: both embed Dilve/Cegal credentials.
		$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "fetch_cover: type=" . $type . " isbn=" . $isbn, 'covers');
		if ( $type == 'dilve' ) {
			$client = new Client(['verify' => false, 'timeout' => 10.0]);
			$coversApiDbLinesManager = new CoversApiDbLinesManager;
			try {
				$response = $client->get($url);
				return $response->getBody();
			} catch( ConnectException $connectException ) {
				$error = ['message'=> $connectException->getMessage()];
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"fetch_cover: connection exception isbn=" . $isbn . " error=" . $error['message'] , 'covers');
				$coversApiDbLinesManager->setError( $isbn, $error['message'] );
				return false;
			} catch ( RequestException $requestException ) {
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
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__,"fetch_cover: request failed isbn=" . $isbn . " error=" . $error['message'], 'covers');
				$coversApiDbLinesManager->setError( $isbn, $error['message'] );
				return false;
			} catch (\Exception $exception) {
				$error['message'] = 'Error: ' . $exception->getMessage();
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "fetch_cover: exception isbn=" . $isbn . " error=" . $error['message'], 'covers');
				$coversApiDbLinesManager->setError( $isbn, $error['message'] );
				return false;
			}
		} else {
			$coversApiDbLinesManager = new CoversApiDbLinesManager;
			$response = wp_remote_get($query, [ 'timeout' => 140 ]);
			// Check if the response is an error
			if (is_wp_error($response)) {
				// Handle error
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "fetch_cover: request failed isbn=" . $isbn . " wp_error=" . $response->get_error_message(), 'covers');
				return false;
			}
			$body = (string) wp_remote_retrieve_body($response);
			$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "cegal response: isbn=" . $isbn . " bytes=" . strlen($body) . " status=" . ( trim($body) === '' ? 'empty' : 'parsed' ), 'covers');
			$xmlString = (string) wp_remote_retrieve_body( $response );
			$xmlString = (string) preg_replace('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]+/', '', $xmlString);
			$xml = simplexml_load_string( $xmlString );
			// Additional checks to ensure the XML and expected fields are present.
			if ( !$xml || empty( $xml->LIBRO->PORTADA->IMAGEN_PORTADA ) || empty( $xml->LIBRO->PORTADA ) ) {
				// Handle error - at most 200 chars of excerpt, never the full body
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "fetch_cover: xml fields missing isbn=" . $isbn . " excerpt=" . substr($body, 0, 200), 'covers');
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
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "create_cover: no data from fetch_cover isbn=" . $isbn , 'covers');
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
				$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "create_cover: isbn=" . $isbn . " file_id=" . $file_id . " already exists" , 'covers');
			} else {
				$file_id = $coversApiDbManager->attachFile( $filepath, $data, $filename );
				$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "create_cover: isbn=" . $isbn . " attached file_id=" . $file_id, 'covers');
			}
			return ( $file_id > 0 ) ? get_post( $file_id ): false;
  	}

	/**
	 * Scan published products without a cover and attach one from Dilve/CEGAL.
	 *
	 * Stats mechanism: pass an array by reference as $stats to receive the
	 * per-call counters ['scanned','attached','invalid_ean','failed','skipped'].
	 * The array is reset on every call. When the argument is omitted the
	 * counters stay local. Return contract is unchanged: false only when the
	 * candidate list is empty, otherwise a JSON string with hasMore/eans/etc.
	 */
	public function scanProducts($log_id, $batch_size = -1, $offset = 0, $type = 'dilve', ?array &$stats = null) {
		global $wpdb;
		$coversApiDbManager = new CoversApiDbManager;
		$coversApiDbLogManager = new CoversApiDbLogManager;
		$coversApiDbLinesManager = new CoversApiDbLinesManager;
		$stats = [
			'scanned' => 0,
			'attached' => 0,
			'invalid_ean' => 0,
			'failed' => 0,
			'skipped' => 0,
		];
		$products = (array) $coversApiDbManager->getProductsWithoutCover();
		$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_products: type=' . $type . ' candidates=' . count($products), 'covers');
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
			$stats['scanned']++;
			// Get ean number from each product;
			if ( $product['ID'] == null ) {
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "scan_products: product id is null", 'covers');
				$stats['failed']++;
				continue;
			}
			$ean = get_post_meta( $product['ID'], '_ean', true );
			// Never log the query URL: it embeds Dilve/Cegal credentials.
			$url = (string) $this->get_query($type, $ean );
			if ( $coversApiDbManager->hasAttachment( $product['ID'] ) ) {
				$this->biblioApi->getLogger()->debug(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'skipped already covered ean=' . $ean . ' id=' . $product['ID'] , 'covers');
				$stats['skipped']++;
				continue;
			}
			if ($this->validateEAN($ean) == false) {
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'invalid ean=' . $ean . ' id=' . $product['ID'], 'covers');
				$stats['invalid_ean']++;
				continue;
			}

			$filepath = (string) sprintf("%s/portadas/%s", wp_upload_dir()['basedir'], $ean.'.jpg');
			$line_id = $coversApiDbLinesManager->insertLinesData($log_id, $ean, $filepath, $type);

			if ( $type == 'dilve' && $ean != '' ) {
				$filepath = (string) sprintf("%s/portadas/%s", wp_upload_dir()['basedir'], $ean.'.jpg');
				$line_id = $coversApiDbLinesManager->insertLinesData($log_id, $ean, $filepath, $type);
				$book = $this->search_dilve($ean);
				if ( $book && is_array($book) && isset($book['cover_url'] ) ) {
					if ($coversApiDbManager->hasAttachment( $product['ID'] ) ) {
						$this->biblioApi->getLogger()->debug(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'skipped already covered ean=' . $ean . ' id=' . $product['ID'], 'covers');
						$stats['skipped']++;
						continue;
					}

					$coversApiDbLinesManager->set_url_origin($line_id, $book['cover_url']);
					$coversApiDbLinesManager->setBook($product['post_title'], $product['ID'], $line_id);

					if ( $cover_post = $this->create_cover( $book['cover_url'], $ean.'.jpg', 'image/jpeg', FALSE, $type ) ) {
						$coversApiDbManager->set_featured_image_for_product($cover_post->ID, $ean);
						$coversApiDbLinesManager->set_url_target($line_id, $product['ID']);
						$coversApiDbManager->save_cover_source_by_product_id( (int) $product['ID'], $book['cover_url'] );
						$attached_id = $cover_post->ID;
					} else {
						$attached_id = false;
					}
					$subtitle_note = '';
					if ( isset($book['subtitle']) && !empty($book['subtitle']) ) {
						// Fill-empty-only: never overwrite an existing subtitle.
						if ( get_post_meta( $product['ID'], '_subtitle', true ) === '' ) {
							update_post_meta( $product['ID'], '_subtitle', $book['subtitle'] );
							$subtitle_note = ' subtitle=updated';
						} else {
							$subtitle_note = ' subtitle=kept_existing';
						}
					}
					$coversApiDbManager->set_covers_url($ean, $book['cover_url']);
					if ( $attached_id ) {
						$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'attached ean=' . $ean . ' id=' . $product['ID'] . ' file_id=' . $attached_id . $subtitle_note, 'covers');
						$stats['attached']++;
					} else {
						$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'failed ean=' . $ean . ' id=' . $product['ID'] . ' reason=create_cover', 'covers');
						$stats['failed']++;
					}
				} else {
					$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'failed ean=' . $ean . ' id=' . $product['ID'] . ' reason=no cover_url', 'covers');
					$stats['failed']++;
				}
			} else {
				if ($file = $this->create_cover( $url, $ean.'.jpg', 'image/jpeg', FALSE, $type ) ) {
					$coversApiDbLinesManager->set_url_origin($line_id, $url);
					$coversApiDbManager->set_featured_image_for_product( $file->ID, $ean );
					$coversApiDbLinesManager->setBook($product['post_title'], $product['ID'], $line_id);
					$coversApiDbLinesManager->set_url_target($line_id, $product['ID']);
					$coversApiDbManager->save_cover_source_by_product_id( (int) $product['ID'], $url );
					$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'attached ean=' . $ean . ' id=' . $product['ID'] . ' file_id=' . $file->ID, 'covers');
					$stats['attached']++;
				} else {
					$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'failed ean=' . $ean . ' id=' . $product['ID'] . ' reason=create_cover', 'covers');
					$stats['failed']++;
				}
				$coversApiDbManager->set_covers_url($ean, $url);
			}
			$coversApiDbLogManager->setLogStatus($log_id, 'processing');
			$response[] = [ 'id' => $product['ID'] ];
			$progress = ( $offset * ($index + 1) / $totalLines ) * 100;
			array_push($eans, $ean);
			$index++;
		}
		$response['hasMore'] = $hasMore;
		$response['eans'] = $eans;
		$response['message'] = "[BIBLIO - Covers Api] " . $batch_size . " books have been processed.";
		if ( $stats['scanned'] > 0 ) {
			$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_products: processed=' . $stats['scanned'] . ' type=' . $type, 'covers');
		}
		$response['progress'] = number_format($progress, 2)." %";
		if ( $hasMore == false ) {
			$coversApiDbLogManager->setLogStatus($log_id, 'processed');
			$coversApiDbLogManager->set_processed_products($log_id, $offset + $index);
		}
		return json_encode( $response );
	}

	/**
	 * Scan published products without a subtitle and fill _subtitle from Dilve
	 * ONIX metadata (fill-empty-only).
	 *
	 * Fully decoupled from the cover pipeline: candidates come from
	 * getProductsWithoutSubtitle() (no _thumbnail_id condition) and this method
	 * never calls getResourceX/fetch_cover/create_cover — metadata only. One
	 * search_dilve() call per product per run, paced with a filterable pause
	 * (biblio_subtitles_scan_interval_ms, default 1000 ms) so the run stays far
	 * under DAPI's rate limits.
	 *
	 * Stats mechanism: pass an array by reference as $stats to receive the
	 * per-call counters ['scanned','filled','skipped_has_subtitle',
	 * 'skipped_no_subtitle_in_onix','invalid_ean','failed'].
	 * The array is reset on every call. When the argument is omitted the
	 * counters stay local. Return contract mirrors scanProducts(): false only
	 * when the candidate list is empty, otherwise a JSON string of $stats.
	 */
	public function scanSubtitles(int $batch_size = -1, int $offset = 0, ?array &$stats = null): mixed {
		$coversApiDbManager = new CoversApiDbManager;
		$stats = [
			'scanned' => 0,
			'filled' => 0,
			'skipped_has_subtitle' => 0,
			'skipped_no_subtitle_in_onix' => 0,
			'invalid_ean' => 0,
			'failed' => 0,
		];
		$products = (array) $coversApiDbManager->getProductsWithoutSubtitle($batch_size, $offset);
		// Never log the query URL: it embeds Dilve credentials.
		$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_subtitles: candidates=' . count($products), 'subtitles');
		if ( empty($products) ) {
			return false;
		}
		$calls_made = 0;
		foreach ( $products as $product ) {
			$stats['scanned']++;
			if ( empty($product['ID']) ) {
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "scan_subtitles: product id is empty", 'subtitles');
				$stats['failed']++;
				continue;
			}
			$ean = get_post_meta( $product['ID'], '_ean', true );
			if ( $ean == '' || $this->validateEAN($ean) == false ) {
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_subtitles: invalid ean=' . $ean . ' id=' . $product['ID'], 'subtitles');
				$stats['invalid_ean']++;
				continue;
			}
			// Rate limit: one getRecordsX call per product, pause between calls.
			// DAPI errors count against the limits, so failures are never retried
			// within a run — one attempt per product.
			if ( $calls_made > 0 ) {
				usleep( (int) apply_filters( 'biblio_subtitles_scan_interval_ms', 1000 ) * 1000 );
			}
			$calls_made++;
			$book = $this->search_dilve($ean);
			if ( is_string($book) ) {
				// DAPI answered with an <error> body; at most 200 chars of text.
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_subtitles: dilve error ean=' . $ean . ' id=' . $product['ID'] . ' error=' . substr($book, 0, 200), 'subtitles');
				$stats['failed']++;
				continue;
			}
			if ( ! is_array($book) ) {
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_subtitles: request failed ean=' . $ean . ' id=' . $product['ID'], 'subtitles');
				$stats['failed']++;
				continue;
			}
			if ( ! isset($book['subtitle']) || $book['subtitle'] === '' ) {
				$this->biblioApi->getLogger()->debug(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_subtitles: no subtitle in onix ean=' . $ean . ' id=' . $product['ID'], 'subtitles');
				$stats['skipped_no_subtitle_in_onix']++;
				continue;
			}
			// Re-check at write time: fill-empty-only against concurrent writes.
			if ( get_post_meta( $product['ID'], '_subtitle', true ) !== '' ) {
				$this->biblioApi->getLogger()->debug(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_subtitles: skipped already has subtitle ean=' . $ean . ' id=' . $product['ID'], 'subtitles');
				$stats['skipped_has_subtitle']++;
				continue;
			}
			if ( update_post_meta( $product['ID'], '_subtitle', $book['subtitle'] ) === false ) {
				$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_subtitles: update_post_meta failed ean=' . $ean . ' id=' . $product['ID'], 'subtitles');
				$stats['failed']++;
				continue;
			}
			$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_subtitles: filled ean=' . $ean . ' id=' . $product['ID'], 'subtitles');
			$stats['filled']++;
		}
		$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'scan_subtitles: processed=' . $stats['scanned'] . ' filled=' . $stats['filled'], 'subtitles');
		return json_encode( $stats );
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

	/**
	 * Attach locally staged cover files (uploads/img) as featured images.
	 *
	 * Stats mechanism: pass an array by reference as $stats to receive the
	 * per-call counters ['candidates','found','attached','already','missing','failed'].
	 * The array is reset on every call. When the argument is omitted the
	 * counters stay local. Return value remains void.
	 */
	public function attach_local_missing_covers(?array &$stats = null){
		$coversApiDbManager = new CoversApiDbManager;
		$stats = [
			'candidates' => 0,
			'found' => 0,
			'attached' => 0,
			'already' => 0,
			'missing' => 0,
			'failed' => 0,
		];
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
		$stats['candidates'] = count($products);
		$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__ , "missing_covers: candidates=" . count($products), "missingcovers");
		foreach ($products as $product) {
			$originalean = get_post_meta($product->ID, '_ean', true);
			$ean = substr($originalean, 0, -1);
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
				$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "missing_covers: id=" . $product->ID . " ean=" . $originalean . " file=absent", "missingcovers");
				$stats['missing']++;
				continue;
			}
			$stats['found']++;
			// Check if the file is an attachment in the media library
			$attachments = $coversApiDbManager->isAttachment($filename);
			if (count($attachments) == 0) {
				// If the file is not an attachment, create it
				$file = $this->create_cover(
					$uploads['baseurl'] . '/img/' . $subfolder . '/' . $filename,
					$ean . '.jpg',
					'image/jpeg',
					false,
					'dilve'
				);
				if ($file) {
					// If the file was created successfully, set it as the featured image for the product
					$coversApiDbManager->set_featured_image_for_product($file->ID, $originalean, 'missingcovers');
					$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "missing_covers: id=" . $product->ID . " ean=" . $originalean . " file=found → attached file_id=" . $file->ID, 'missingcovers');
					$stats['attached']++;
				} else {
					$this->biblioApi->getLogger()->error(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "missing_covers: id=" . $product->ID . " ean=" . $originalean . " file=found reason=create_cover", 'missingcovers');
					$stats['failed']++;
				}
			} else {
				// If the file is already an attachment, set it as the featured image for the product
				$coversApiDbManager->set_featured_image_for_product($attachments[0]->ID, $originalean, 'missingcovers');
				$this->biblioApi->getLogger()->info(__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "missing_covers: id=" . $product->ID . " ean=" . $originalean . " file=found already attached attachment=" . $attachments[0]->ID, 'missingcovers');
				$stats['already']++;
			}
		}
	}
}