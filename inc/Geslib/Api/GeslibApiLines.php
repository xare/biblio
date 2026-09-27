<?php

namespace Inc\Geslib\Api;

use Inc\Biblio\Api\BiblioApi;
use Inc\Geslib\Api\GeslibApiDbLinesManager;
use Inc\Geslib\Api\GeslibApiDbManager;
use Inc\Geslib\Config\LineTypes;
use WP_CLI;

class GeslibApiLines {
	static $productDeleteKeys = LineTypes::PRODUCT_DELETE_KEYS;
	static $authorDeleteKeys = LineTypes::AUTHOR_DELETE_KEYS;
	static array $editorialDeleteKeys = LineTypes::EDITORIAL_DELETE_KEYS;
	static array $categoriaDeleteKeys = LineTypes::CATEGORIA_DELETE_KEYS;
	static array $productKeys = LineTypes::PRODUCT_KEYS;
	static array $editorialKeys = LineTypes::EDITORIAL_KEYS;
	static array $coleccionKeys = LineTypes::COLECCION_KEYS;
	static array $categoriaKeys = LineTypes::CATEGORIA_KEYS;
	static array $authorKeys = LineTypes::AUTHOR_KEYS;
	static array $lineTypes = LineTypes::LINE_TYPES;
	private $db;
	private string $mainFolderPath;
	private string $processedFolderPath;
	private $geslibSettings;
	private $geslibApiSanitize;
	private $biblioApi;
	
	public function __construct() {
		$this->geslibSettings = get_option('geslib_settings');
		$this->mainFolderPath = WP_CONTENT_DIR . "/uploads/".$this->geslibSettings['geslib_folder_index']."/";
		$this->processedFolderPath = $this->mainFolderPath . 'processed/';
		$this->db = new GeslibApiDbManager();
		$this->geslibApiSanitize = new GeslibApiSanitize();
		$this->biblioApi = new BiblioApi;
	}

	/**
	 * storeToLines
	 *
	 * Send the data from the INTER*** file to the geslib_queue(type='store_lines') table.
	 *
	 * @return int
	 */
	public function storeToLines(int $log_id): int{
		$geslibApiDbLogManager = new GeslibApiDbLogManager;
		$geslibApiDbQueueManager = new GeslibApiDbQueueManager;
		$geslibApiDbProductsManager = new GeslibApiDbProductsManager;
		$startTime = microtime(true);

		// Pre-load ALL product stocks into memory for batch B-line filtering
		$stockMap = $geslibApiDbProductsManager->loadStockMap();
		$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Pre-loaded stock map: ' . count($stockMap) . ' products', 'geslib');

		// 1. Read the log table
		$filename = $geslibApiDbLogManager->getGeslibLoggedFilename( $log_id );
		$fullPath = $this->mainFolderPath . $filename;
		$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Full path: ' . $fullPath , 'geslib');

		// 2. Read the file and store in lines table
		if ( pathinfo( $fullPath, PATHINFO_EXTENSION ) === 'zip' ) {
			$geslibReadFile = new GeslibApiReadFiles;
			$filename = $geslibReadFile->unzipFile( $fullPath );
		}

		// Fallback: check processed/ folder if file was already moved
		if ( ! file_exists( $fullPath ) && file_exists( $this->processedFolderPath . $filename ) ) {
			$fullPath = $this->processedFolderPath . $filename;
			$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'File not in main folder, using processed/ fallback: ' . $fullPath, 'geslib');
		}

		if ( ! file_exists( $fullPath ) ) {
			$this->biblioApi->getLogger()->debug('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, "File not found in main or processed folder: {$fullPath}", 'geslib');
			return $log_id;
		}

		$lines = file( $fullPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		$batch_size = 300;
		$batch = [];
		$included = 0;
		$excluded = 0;
		$excludedByType = [];
		$totalLines = count( $lines );

		foreach ($lines as $line) {
			$line = $this->sanitizeLine( $line );
			$line_array = explode('|', $line);

			if ( 
				$this->isUnnecessaryLine( $line ) 
				|| !in_array( $line_array[0], self::$lineTypes) 
				|| $this->isInEditorials( $line ) 
				|| $this->isInAutors( $line ) 
				|| ($line_array[0] == 'B' 
					&& !$geslibApiDbProductsManager->stockHasChanged($line_array[1], $line_array[2], $stockMap))
			) {
				$excluded++;
				$type = $line_array[0] ?? 'unknown';
				$excludedByType[$type] = ($excludedByType[$type] ?? 0) + 1;
				continue;
			}
			$index = ( in_array( $line_array[0], ['6E', '6TE', 'AUTBIO', 'B','LA'] ) ) ? 1 : 2;
			$entity = match ( $line_array[0] ) {
				'1L' => 'editorials',
				'GP4' => 'product',
				'AUT' => 'autors',
				'2' => 'colecciones',
				'3' => 'product_cat',
				'5' => 'categories',
				'B' => 'product',
				'LA' => 'product',
				default => 'none',
			};
			$action = match( $line_array[1] ) {
				'A' => 'crear',
				'M' => 'actualizar',
				'B' => 'borrar',
				default => 'adjuntar',
			};
			$item = [
				'log_id' => $log_id,
				'geslib_id' => $line_array[$index],
				'type' => 'store_lines',
				'entity' => $entity,
				'action' => $action,
				'data' => $line,
			];
			$batch[] = $item;
			if (count($batch) >= $batch_size) {
				$geslibApiDbQueueManager->insertLinesIntoQueue( $batch );
				$batch = [];
			}
			$included++;
		}
		// Don't forget the last batch
		if ( !empty( $batch ) ) {
			$geslibApiDbQueueManager->insertLinesIntoQueue( $batch );
		}

		// Per-file summary
		$elapsed = round(microtime(true) - $startTime, 1);
		$excludedSummary = '';
		if (!empty($excludedByType)) {
			arsort($excludedByType);
			$parts = [];
			foreach (array_slice($excludedByType, 0, 5, true) as $type => $count) {
				$parts[] = "{$type}:{$count}";
			}
			$excludedSummary = ' (' . implode(', ', $parts) . ')';
		}
		$this->biblioApi->getLogger()->debug('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__,
			"{$filename}: {$totalLines} lines — {$included} included, {$excluded} excluded{$excludedSummary} [{$elapsed}s]", 'geslib');

    	return $log_id;
	}

	public function sanitizeLine(string $line):string {
		// Split the line into its components
		$line_items = explode('|', $line);

		// Sanitize each component
		$sanitized_items = array_map(function($line_item) {
			if(is_string($line_item))
				return $this->geslibApiSanitize->utf8_encode($line_item);
			return $line_item;
		}, $line_items);

		// Join the components back together
		$sanitized_line = implode('|', $sanitized_items);

		return $sanitized_line;
	}


	/**
	 * readLine
	 *
	 * @param  string $line
	 * @param  int $log_id
	 * @return void
	 */
	public function readLine( string $line, int $log_id ) :void {
		$geslibApiDbQueueManager = new GeslibApiDbQueueManager();
		$data = explode( '|', $line ) ;
		array_pop($data);

		if( defined('GESLIB_DEBUG_LINES') && GESLIB_DEBUG_LINES ) {
			$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Processing line: ' . $line , 'geslib');
		}

		if( in_array($data[0], self::$lineTypes ) ) {
			$function_name = 'process' . $data[0];
			if ( method_exists( $this, $function_name ) ) {
				$this->{$function_name}($data, $log_id);
			}
			$index = (in_array( $data[0] ,['6E', '6TE','BIC','B','LA'])) ? 1 : 2;
			$geslibApiDbQueueManager->deleteItemFromQueue('store_lines', $log_id, (int) $data[$index]);
		}

	}

	/**
	 * processGP4
	 * // LIBROS
	 * //"type" | "action" | "geslib_id" |	"description" |	"author" | "pvp_ptas" |	"isbn" | "ean" |"num_paginas" |	"num_edicion" |	"origen_edicion" |"fecha_edicion" |	"fecha_reedicion" |	"año_primera_edicion" |"año_ultima_edicion" |"ubicacion" |"stock" |	"materia" |	"fecha_alta" |	"fecha_novedad" |"Idioma" |	"formato_encuadernacion" |"traductor" |"ilustrador" |"colección" |"numero_coleccion" |"subtitulo" |	"estado" |	"tmr" |	"pvp" |	"tipo_de_articulo" |"clasificacion" |"editorial" |	"pvp_sin_iva" |	"num_ilustraciones" |"peso" |"ancho" |"alto" |		"fecha_aparicion" |	"descripcion_externa" |	"palabras_asociadas" |			"ubicacion_alternativa" |"valor_iva" |"valoracion" |"calidad_literaria" |	"precio_referencia" | "cdu" |"en_blanco" |"libre_1" |"libre_2" | 			"premiado" |"pod" | "distribuidor_pod" | "codigo_old" | "talla" |			"color" |"idioma_original" |"titulo_original" |	"pack" |"importe_canon" |	"unidades_compra" |"descuento_maximo"
	 * // GP4|A|17|BODAS DE SANGRE|GARRIGA MART�NEZ, JOAN|3660|978-84-946952-8-5|9788494695285|56|01||20180101||    |    ||1|06|20230214||003|02|BROGGI RULL, ORIOL||1||APUNTS I CAN�ONS DE JOAN GARRIGA SOBRE TEXTOS DE FEDERICO GARC�A LORCA (A PARTIR|0|0,00|22,00|L0|1|15|21,15|||210|148|||||4,00|||0,00|||||N|N||12530|||001||N||1|100,00|
	 *
	 * @param  mixed $data
	 * @param  int $log_id
	 * @return void
	 */
	private function processGP4( array $data, int $log_id ) {
		$geslibApiDbLinesManager = new GeslibApiDbLinesManager;
		if ($data[1] === 'B') {
			$keys = self::$productDeleteKeys;
		} elseif (in_array($data[1], ['A', 'M'])) {
			$keys = self::$productKeys;
		}
		if (! isset($keys)) return false;
		$content_array = array_combine($keys, $data);
		$content_array = $this->geslibApiSanitize->sanitize_content_array($content_array);
		$geslibApiDbLinesManager->insertData( $content_array, $data[1], $log_id, 'product' );
	}

	/**
	 * process6E
	 * Procesa las l�neas 6E aqu�
	 * 6E|Articulo|Contador|Texto|
	 * 6E|1|1|Els grans mitjans ens han repetit fins a l'infinit escenes de mort i destrucci� a Gaza, per� ens han amagat la quotidianitat m�s extraordin�ria. Viure morir i n�ixer a Gaza recull un centenar de fotografies que ens mostren les meravelles que David Segarra es va trobar enmig de la trag�dia: la capacitat de viure, d'estimar, de resistir i de sobreviure malgrat l'horror.
	 *
	 * @param  mixed $data
	 * @param  int $log_id
	 * @return void
	 */
	private function process6E($data, int $log_id) {
		$geslib_id = $data[1];
		$content_array['sinopsis'] = $data[3];
		$content_array = $this->geslibApiSanitize->sanitize_content_array( $content_array );
		$this->mergeContent( $geslib_id, $content_array, 'product');
	}

	/**
	 * process6TE
	 * Procesa las líneas 6TE aquí
	 * 6TE|Articulo|Contador|Texto|
	 * 6TE|1|1|Els grans mitjans ens han repetit fins a l'infinit escenes de mort i destrucció a Gaza, però ens han amagat la quotidianitat més extraordinària. Viure morir i néixer a Gaza recull un centenar de fotografies que ens mostren les meravelles que David Segarra es va trobar enmig de la tragèdia: la capacitat de viure, d'estimar, de resistir i de sobreviure malgrat l'horror.
	 *
	 * @param  mixed $data
	 * @param  int $log_id
	 * @return void
	 */
	/**
	 * process1L
	 * EDITORIAL
	 * 1L|B|codigo_editorial
	 * 1L|Tipo movimiento|Codigo_editorial|Nombre|nombre_externo|País|url|
	 * 1L|A|1|VARIAS|VARIAS|ES|
	 * @param  array $data
	 * @param  int $log_id
	 * @return void
	 */
	private function process1L( array $data, int $log_id ): void {
		$geslibApiDbLinesManager = new GeslibApiDbLinesManager;
		$keys = self::$editorialKeys;
		if (count($keys) > count($data)) {
            // Take only the keys that correspond to the length of $data
            $keys = array_slice($keys, 0, count($data));
        }
		if ($data[1] === 'B') {
			$keys = self::$editorialDeleteKeys;
		}
		$content_array = array_combine($keys, $data);
		$content_array = $this->geslibApiSanitize->sanitize_content_array( $content_array );
		$this->biblioApi->debug_log('INFO '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Editorial: ' . $data[1] . '|' . ($data[2] ?? '?') . '|' . ($data[3] ?? ''), 'geslib');
		$geslibApiDbLinesManager->insertData( $content_array, $data[1], $log_id , 'editorial');
	}

	/**
	 * process2
	 * COLECCION EDITORIAL
	 * 2|B|codigo_editorial
	 * 2|Tipo movimiento|Codigo_editorial|Nombre|nombre_externo|País|
	 * 2|A|1|VARIAS|VARIAS|ES|
	 * @param  array $data
	 * @param  int $log_id
	 * @return void
	 */
	private function process2( array $data, int $log_id ): void {
		$geslibApiDbLinesManager = new GeslibApiDbLinesManager;
		$keys = self::$coleccionKeys;
		if ($data[1] === 'B') {
			$keys = self::$editorialDeleteKeys;
		}
		$content_array = array_combine($keys, $data);
		$content_array = $this->geslibApiSanitize->sanitize_content_array( $content_array );
		$geslibApiDbLinesManager->insertData( $content_array, $data[1], $log_id , 'coleccion');
	}

	/**
	 * process3
	 * Materias
	 * Add categories
	 * 3|A|01|Cartes|||
	 *
	 * @param  array $data
	 * @param  int $log_id
	 * @return void
	 */
	private function process3( array $data, int $log_id ) {
		$geslibApiDbLinesManager = new GeslibApiDbLinesManager;
		$keys = self::$categoriaKeys;
		if ($data[1] === 'B') {
			$keys = self::$categoriaDeleteKeys;
		}
		if ( $data[2] == '') return false;

		$content_array = array_combine( $keys, $data );
		$content_array = $this->geslibApiSanitize->sanitize_content_array( $content_array );
		$geslibApiDbLinesManager->insertData( $content_array, $data[1], $log_id , 'product_cat');
	}

	/**
	 * process5
	 * Add a category to to a product
	 *  “5”|Código de categoría (varchar(12))|Código de producto|
	 *  5|17|1|
	 * @param  mixed $data
	 * @param  mixed $log_id
	 * @return void
	 */
	private function process5( $data, $log_id ) {
		$geslib_category_id = $data[1];
		$geslib_product_id = $data[2];
		$content_array = [];
		if($geslib_category_id !== 0 && $geslib_category_id != '') {
			$content_array['categories'][$geslib_category_id] = $geslib_product_id;
			$this->mergeContent($geslib_product_id, $content_array, 'product', $log_id);
		}
	}

	/**
	 * processAUT
	 * Procesa las líneas AUT
	 * “AUT”|Acción|GeslibID|Nombre del autor
	 * AUT|A|2806|HILAL, JAMIL|
	 * "AUT"|B|GeslibId
	 *
	 * @param  mixed $data
	 * @param  mixed $log_id
	 * @return void
	 */
	private function processAUT( $data, $log_id ) {
		$geslibApiDbLinesManager = new GeslibApiDbLinesManager;
		if (in_array( $data[1], ['A','M'] )){
			// Insert or Update
			$content_array = array_combine( self::$authorKeys, $data );
			$content_array = $this->geslibApiSanitize->sanitize_content_array($content_array);
		} elseif ($data[1] == 'B' ){
			// Delete
			$content_array = array_combine( self::$authorDeleteKeys, $data );
		}
		$geslibApiDbLinesManager->insertData( $content_array, $data[1], $log_id, 'autors' );
	}

/**
	 * processLA
	 * Add an author to to a product
	 * “LA”|Código de producto|código del autor (varchar(12))| Tipo de autor (A Autor, I Ilustrador, IC Ilustrador contraportada, IP ilustrador Portada, T traductor) | Orden
	 *	LA|9047|6345|A|1|
	 *
	 * @param  mixed $data
	 * @param  int $log_id
	 * @return void
	 */
	private function processLA( $data, int $log_id ) {
		$geslib_author_id = $data[2];
		$geslib_product_id = $data[1];
		$content_array = [];
		if($geslib_product_id == 0 && $geslib_product_id == '') { return false; }

		$content_array['authors'][$geslib_author_id] = $geslib_product_id;
		$this->mergeContent($geslib_product_id, $content_array, 'product', $log_id);
	}

	/**
	 * processB
	 * //B|21544|1
	 * Añade datos de stock
	 * @param  mixed $data
	 * @param  mixed $log_id
	 * @return boolean
	 */
	private function processB( $data, int $log_id ): bool {
		$geslibApiDbLinesManager = new GeslibApiDbLinesManager;
		$geslibApiDbProductsManager = new GeslibApiDbProductsManager;
		$content_array['stock'] = $data[2];
		$content_array['geslib_id'] = $data[1];
		if ($geslibApiDbProductsManager->check_product_stock_by_geslib_id($data[1], $data[2]) === false ) 
			return false;

		$geslibApiDbLinesManager->insertData($content_array, 'stock', $log_id, 'product');
		return true;
	}


	/**
	 * mergeContent
	 * this function is called when the product has been created but we need to add more data to its content json string
	 *
	 * @param  int $geslib_id
	 * @param  array $new_content_array
	 * @param  string $type
	 * @return mixed
	 */
	private function mergeContent(
						int $geslib_id,
						array $new_content_array,
						string $entity,
						int $log_id = 0,
						string $action = '' ) {
		$geslibApiDbLinesManager = new GeslibApiDbLinesManager;
		//1. Get the content given the $geslib_id
		$original_content = $geslibApiDbLinesManager->fetchContent( $geslib_id, $entity );
		if ( !$original_content ) {
			$this->biblioApi->debug_log('ERROR '.__CLASS__. ':'.__LINE__.' '.__FUNCTION__, 'Content not found for geslib_id: ' . $geslib_id, 'geslib');
			return false;
		}

		$original_content_array = json_decode( $original_content, true);
		if( isset( $new_content_array['categories'] ) ) {
			if (
				isset( $original_content_array['categories'] )
				&& count( $original_content_array['categories'] ) > 0
				) {
					$original_content_array['categories'] = array_merge( $original_content_array['categories'], $new_content_array['categories'] );
					array_push( $original_content_array['categories'], $new_content_array['categories'] );
			} elseif ( isset( $new_content_array['categories'] ) ) {
				$original_content_array['categories'] = $new_content_array['categories'];
			}
		}

		if( isset( $new_content_array['authors'] ) ) {
			if (
				isset( $original_content_array['authors'] )
				&& count( $original_content_array['authors'] ) > 0
				) {
					$original_content_array['authors'] = array_merge( $original_content_array['authors'], $new_content_array['authors'] );
					array_push( $original_content_array['authors'], $new_content_array['authors'] );
				} elseif ( isset( $new_content_array['authors'] ) ) {
					$original_content_array['authors'] = $new_content_array['authors'];
				}
		}
		$fields = ['sinopsis','biografia'];
		foreach( $fields as $field ) {
			if ( !isset( $original_content_array[$field] )
			&& isset( $new_content_array[$field] )) {
				$original_content_array[$field] = $new_content_array[$field];
			}
		}

		$content = json_encode($original_content_array);
		if ( ! $content ) return false;

		$geslibApiDbLinesManager->updateGeslibLines( $geslib_id, $entity, $content );
	}

	/**
	 * unnecessaryLine
	 *
	 * @param  string $line
	 * @return boolean
	 */
	public function isUnnecessaryLine( string $line ) :bool {
		return strpos($line, '< Genérica >') !== false;
	}

	/**
	 * Check if the editorial is in the taxonomy.
	 *
	 * @param string $line
	 *   The input line, e.g., '1L|A|216|AGUILAR'.
	 *
	 * @return bool
	 *   TRUE if the editorial is in the taxonomy, FALSE otherwise.
	 */
	public function isInEditorials( string $line ) :bool {
		[$type, $action, $geslib_id] = explode('|', $line) + [null, null, null];
		return $type === '1L' && $action === 'A' &&
			!empty(get_terms( [
				'taxonomy'   => 'editorials', // replace with your actual taxonomy name
				'hide_empty' => false,
				'meta_query' => [
					[
						'key'     => 'geslib_id',
						'value'   => $geslib_id,
						'compare' => '=',
					],
				],
			]));
	}

	/**
	 * Check if the author is in the taxonomy.
	 * “AUT”|Acción|GeslibID|Nombre del autor
	 * AUT|A|2806|HILAL, JAMIL|
	 * "AUT"|B|GeslibId
	 * @param string $line
	 *   The input line, e.g., '1L|A|216|AGUILAR'.
	 *
	 * @return bool
	 *   TRUE if the autor is in the taxonomy, FALSE otherwise.
	 */
	public function isInAutors( string $line ) :bool {
		[$type, $action, $geslib_id] = explode('|', $line) + [null];
		if($action === 'B') return false;
		return $type === 'AUT' && $action === 'A' &&
			!empty(get_terms( [
				'taxonomy'   => 'autors', // replace with your actual taxonomy name
				'hide_empty' => false,
				'meta_query' => [
					[
						'key'     => 'geslib_id',
						'value'   => $geslib_id,
						'compare' => '=',
					],
				],
			]));
	}
	/**
	 * Check if the line type is in product key and return the line if true.
	 *
	 * @param string $line
	 *   The input line, e.g., 'Type|Other|Data'.
	 *
	 * @return bool
	 *   The input line if the type is in product key, FALSE otherwise.
	 */
	public function isInProductKey(string $line ): bool {
		return in_array( explode( '|', $line )[0], self::$lineTypes);
	}

}