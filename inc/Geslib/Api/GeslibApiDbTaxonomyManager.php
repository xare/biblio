<?php

namespace Inc\Geslib\Api;

use Exception;
use Inc\Biblio\Api\BiblioApi;

class GeslibApiDbTaxonomyManager extends GeslibApiDbManager {

	private $BiblioApi;

	public function __construct() {
		$this->BiblioApi = new BiblioApi;
	}
    public function storeEditorials( int $geslib_id, $editorial ) {
		$term_name = $editorial->content;
		$term_slug = $this->_create_slug( $term_name );
		$term_description = $term_name;
		$term = term_exists( $term_name, 'editorials' ); // check if term already exists
		if ( 0 !== $term && null !== $term ) {
			// If the term exists, update it
			$term_data = wp_update_term( $term['term_id'], 'editorials', [
				'name' => $term_name,
				'slug' => $term_slug,
				'description' => $term_description,
			]);
    	} else {
        	// Otherwise, insert a new term
        	$term_data = wp_insert_term(
							$term_name,   // the term
							'editorials', // the taxonomy
							[
								'description'=> $term_description,
								'slug' => $term_slug,
							]);
    	}

		add_term_meta($term_data['term_id'],'editorial_geslib_id', $editorial->geslib_id);
		$editorial_geslib_id = get_term_meta( $term_data['term_id'], 'editorial_geslib_id', true );

        // Check for errors
        if ( is_wp_error($term_data) ) {
			$this->BiblioApi->debug_log("Geslib Taxonomy Manager reorganizeProductCategories", $term_data->get_error_message() );
		   	return false;
        }
		return get_term($term_data['term_id'], 'editorials');
    }

	public function storeColecciones( int $geslib_id, $coleccion ) {
		$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeColecciones", var_export($coleccion, true) );
		$coleccion = json_decode($coleccion);
		$term_name = $coleccion->name;
		if ( $coleccion->content === null ){
			$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeColecciones", var_export($coleccion, true) );
			return false;
		}
		$term_slug = $this->_create_slug( $term_name );
		$term_description = $term_name;
		$term = term_exists( $term_name, 'colecciones' ); // check if term already exists
		if ( 0 !== $term && null !== $term ) {
			// If the term exists, update it
			$term_data = wp_update_term( $term['term_id'], 'colecciones', [
				'name' => $term_name,
				'slug' => $term_slug,
				'description' => $term_description,
			]);
    	} else {
        	// Otherwise, insert a new term
        	$term_data = wp_insert_term(
							$term_name,   // the term
							'colecciones', // the taxonomy
							[
								'description'=> $term_description,
								'slug' => $term_slug,
							]);
    	}

		add_term_meta($term_data['term_id'], 'coleccion_geslib_id', $coleccion->geslib_id);
		$coleccion_geslib_id = get_term_meta( $term_data['term_id'], 'coleccion_geslib_id', true );

        // Check for errors
        if ( is_wp_error($term_data) ) {
            // Handle the error here
			$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeColecciones", $term_data->get_error_message() );
			return false;
        }
		return get_term($term_data['term_id'], 'colecciones');
    }

	public function storeAuthors( int $geslib_id, $author ) {
		$term_name = $author->content;
		$geslibApiSanitize = new GeslibApiSanitize;
		$term_name = $geslibApiSanitize->utf8_encode($term_name);
		$term_slug = $this->_create_slug( $term_name );
		$term_description = $term_name;
		$term = term_exists( $term_name, 'autors' ); // check if term already exists
		if ( 0 !== $term && null !== $term ) {
			// If the term exists, update it
			$term_data = wp_update_term( $term['term_id'], 'autors', [
				'name' => $term_name,
				'slug' => $term_slug,
				'description' => $term_description,
			]);
    	} else {
        	// Otherwise, insert a new term
        	$term_data = wp_insert_term(
							$term_name,   // the term
							'autors', // the taxonomy
							[
								'description'=> $term_description,
								'slug' => $term_slug,
							]);
    	}

		add_term_meta($term_data['term_id'],'autor_geslib_id', $author->geslib_id);

        // Check for errors
        if ( is_wp_error($term_data) ) {
            $this->BiblioApi->debug_log("Geslib Taxonomy Manager storeAutors", $term_data->get_error_message() );
			return false;
        }
		return get_term($term_data['term_id'], 'autors');
    }

    /**
     * storeCategory
     *
     * @param  int $geslib_id
     * @param  mixed $content
     * @return mixed
     */
    public function storeCategory( int $geslib_id, $content): mixed{
		$product_category = json_decode( $content );
		$geslibApiSanitize = new GeslibApiSanitize;
		$term_name = $geslibApiSanitize->utf8_encode($product_category->name);
		$term_slug = $this->_create_slug( $term_name );
		$term_description = $term_name;
		if( !term_exists( $term_name, 'product_cat' )) {
			// Create the category
			// TODO: No asume bien contenidos con acentos y signos raros.
			$result = wp_insert_term(
				$term_name, // the term
				'product_cat', // the taxonomy
				[
					'description' => 'Imported category',
					'slug'        => $term_slug
					// you can add other properties here as per your needs
				]
			);
			add_term_meta($result['term_id'], 'category_geslib_id', $product_category->geslib_id);
			$category_geslib_id = get_term_meta( $result['term_id'], 'category_geslib_id', true );

			// Check for errors
			if (is_wp_error($result)) {
				// Handle error here
				$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeCategory", $result->get_error_message() );
				return false;
			}

			// Return the created category
			return get_term($result['term_id'], 'product_cat');
		} else {
			$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeCategory", "Category with $geslib_id geslib_id number already exists" );
			return false;
		}
	}
	public function storeEditorial( $geslib_id, $content){
		$editorial = json_decode( $content);
		if ( $editorial->name === null ){
			$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeEditorial", var_export( $editorial, true) );
			return false;
		}
        $geslibApiSanitize = new GeslibApiSanitize();
		$term_name = $geslibApiSanitize->utf8_encode($editorial->name);
		$term_slug = $this->_create_slug( $term_name );
		$term_description = $term_name;
		$term = term_exists( $term_name, 'editorials' ); // check if term already exists
		if ( 0 !== $term && null !== $term ) {
			// If the term exists, update it
			$term_data = wp_update_term( $term['term_id'], 'editorials', [
				'name' => $term_name,
				'slug' => $term_slug,
				'description' => $term_description,
			]);
			$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeEditorial", var_export( $term_data, true) );
			if( is_wp_error($term_data) ) return false;
			update_term_meta($term_data['term_id'], 'editorial_geslib_id', $geslib_id);
    	} else {
        	// Otherwise, insert a new term
        	$term_data = wp_insert_term(
							$term_name,   // the term
							'editorials', // the taxonomy
							[
								'description'=> $term_description,
								'slug' => $term_slug,
							]);
			add_term_meta($term_data['term_id'],'editorial_geslib_id', $editorial->geslib_id);
    	}
		if ( is_wp_error($term_data) ) {
            // Handle the error here
			$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeEditorial", $term_data->get_error_message() );
			return false;
		}
		return get_term($term_data['term_id'], 'editorials');
	}
    /**
     * storeAuthor
     *
     * @param  int $geslib_id
     * @param  string $content
     * @return mixed
     */
    public function storeAuthor( int $geslib_id, string $content): mixed {
		$author = json_decode( $content );
		if ( $author === null ) return false;
		$geslibApiSanitize = new GeslibApiSanitize;
		$term_name = $geslibApiSanitize->utf8_encode($author->name);
		if ( $term_name === null ) return false;
		$term_slug = $this->_create_slug( $term_name );
		$term_description = $term_name;
		$term = term_exists( $term_name, 'autors' ); // check if term already exists
		if ( 0 !== $term && null !== $term ) {
			// If the term exists, update it
			try {
				$term_data = wp_update_term( $term['term_id'], 'autors', [
					'name' => $term_name,
					'slug' => $term_slug,
					'description' => $term_description,
				]);
				if (is_wp_error($term_data)) {
					$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeAuthor", $term_data->get_error_message() );
					return false;
				}
				$term_meta = update_term_meta($term_data['term_id'], 'author_geslib_id', $geslib_id);
                return true;
			} catch (\Exception $exception) {
				$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeAuthor", $exception->getMessage() );
				return false;
			}
    	} else {
        	// Otherwise, insert a new term
        	$term_data = wp_insert_term(
							$term_name,   // the term
							'autors', // the taxonomy
							[
								'description'=> $term_description,
								'slug' => $term_slug
							]);
			add_term_meta($term_data['term_id'],'author_geslib_id', $geslib_id);
    	}
        // Check for errors
        if ( is_wp_error($term_data) ) {
            // Handle the error here
			$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeAuthor", $term_data->get_error_message() );
			return false;
        }
		return get_term($term_data['term_id'], 'autors');
	}

	/**
     * storeColeccion
     *
     * @param  int $geslib_id
     * @param  string $content
     * @return mixed
     */
    public function storeColeccion( int $geslib_id, string $content): mixed {
		$coleccion = json_decode( $content );
		if ( $coleccion === null ) return false;
		$geslibApiSanitize = new GeslibApiSanitize;
		$term_name = $geslibApiSanitize->utf8_encode($coleccion->name);
		if ( $term_name === null ) return false;
		$term_slug = $this->_create_slug( $term_name );
		$term_description = $term_name;
		$term = term_exists( $term_name, 'colecciones' ); // check if term already exists
		if ( 0 !== $term && null !== $term ) {
			// If the term exists, update it
			try {
				$term_data = wp_update_term( $term['term_id'], 'colecciones', [
					'name' => $term_name,
					'slug' => $term_slug,
					'description' => $term_description,
				]);
				if (is_wp_error($term_data)) {
					$this->BiblioApi->debug_log("Geslib Taxonomy Manager store Coleccion", $term_data->get_error_message() );
					return false;
				}
				$term_meta = update_term_meta($term_data['term_id'], 'author_geslib_id', $geslib_id);
                return true;
			} catch (\Exception $exception) {
				$this->BiblioApi->debug_log("Geslib Taxonomy Manager store Coleccion", $exception->getMessage() );
				return false;
			}
    	} else {
        	// Otherwise, insert a new term
			try {
        		$term_data = wp_insert_term(
								$term_name,   // the term
								'colecciones', // the taxonomy
								[
									'description'=> $term_description,
									'slug' => $term_slug
								]);
				if ( is_wp_error($term_data) ) {
					// Handle the error here
					$this->BiblioApi->debug_log("Geslib Taxonomy Manager store Coleccion", $term_data->get_error_message() );
					return false;
				}
				add_term_meta($term_data['term_id'],'coleccion_geslib_id', $geslib_id);
				return true;
			} catch(Exception $exception) {
				$this->BiblioApi->debug_log("Geslib Taxonomy Manager store Coleccion", $exception->getMessage() );
				return false;
			}
    	}
        // Check for errors

		return get_term($term_data['term_id'], 'colecciones');
	}


    /**
     * Stores a product category in the WordPress database.
     *
     * Checks if the category already exists, and if not, creates a new one.
     * Also adds a term meta to store the Geslib ID of the category.
     *
     * @param object $product_category The product category object to store.
     * @throws Exception If an error occurs during the category creation process.
     * @return mixed The created category term object, or false on failure.
     */
    public function storeProductCategory($product_category): mixed {
        $geslibApiSanitize = new GeslibApiSanitize;
		// Make sure the category doesn't already exist
		if( !term_exists( $geslibApiSanitize->utf8_encode( $product_category->content ), 'product_cat' )) {
			// Create the category
			// TODO: No asume bien contenidos con acentos y signos raros.
			$result = wp_insert_term(
				$geslibApiSanitize->utf8_encode( $product_category->content ), // the term
				'product_cat', // the taxonomy
				[
					'description' => 'Imported category',
					'slug'        => sanitize_title($product_category->content)
					// you can add other properties here as per your needs
				]
			);
			add_term_meta($result['term_id'],'category_geslib_id', $product_category->geslib_id);
			$category_geslib_id = get_term_meta( $result['term_id'], 'category_geslib_id', true );

			// Check for errors
			if (is_wp_error($result)) {
				// Handle error here
				$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeProductCategory", $result->get_error_message() );
				return false;
			}

			// Return the created category
			return get_term($result['term_id'], 'product_cat');
		} else {
			$this->BiblioApi->debug_log("Geslib Taxonomy Manager storeProductCategory", "Category already exists" );
			return false;
		}
	}

	/**
	 * Reorganizes product categories by updating the parent category of each term.
	 *
	 * This function iterates over all product categories, extracts the parent category's geslib_id,
	 * finds the parent term based on the geslib_id, and updates the term's parent with the parent term.
	 *
	 * @throws Exception If an error occurs during the term update process.
	 * @return void
	 */
	public function reorganizeProductCategories() {
		$terms = get_terms([
			'taxonomy' => 'product_cat',
			'hide_empty' => false,
		]);

		foreach ($terms as $term) {

			// Get the custom field value
			$category_geslib_id = get_term_meta($term->term_id, 'category_geslib_id', true);

			// Extract the parent category's geslib_id
			$parent_category_geslib_id = substr($category_geslib_id, 0, -2);
			$this->BiblioApi->debug_log('Taxonomy Manager  - reorganizeProductCategories','category_geslib_id: '.$category_geslib_id.' - parent_category_geslib_id: '.$parent_category_geslib_id);
			// Find the parent term based on category_geslib_id
			if ( $parent_category_geslib_id != '' ) {
				$args = [
							'taxonomy' => 'product_cat',
							'hide_empty' => false,
							'meta_query' => [
												[
													'key' => 'category_geslib_id',
													'value' => $parent_category_geslib_id,
													'compare' => '='
												]
							]
						];
				$parent_terms = get_terms($args);

				if (!empty($parent_terms) && !is_wp_error($parent_terms)) {
					foreach($parent_terms as $parent_term) {
						if ( $parent_term != '' ) {
							// Update the term's parent with the parent term
							wp_update_term( $term->term_id, 'product_cat', [ 'parent' => $parent_term->term_id ] );
						}
					}
				} else {
					$this->BiblioApi->debug_log('Taxonomy Manager  - reorganizeProductCategories','No parent terms found');
					continue;
				}
			}
		}
	}

	public function removeUncategorizedCategory() {
		$geslibApi = new GeslibApi();
		// Get all products
		$args = array(
			'post_type' => 'product',
			'posts_per_page' => -1,
			'fields' => 'ids',
		);

		// Loop for each product
		$products = get_posts($args);
		$geslibApi->biblio_debug_log('INFO : GeslibApi removeUncategorizedCategory', "We are going to remove Uncategorized category from products. TOTAL: ".count($products));
		foreach ($products as $product) {
			// Get all product categories
			$categories = get_the_terms($product, 'product_cat');			
			// Remove uncategorized category
			// For each product get all the categories.
			if(count($categories) == 1 ) continue; 
			foreach ($categories as $category) {
				// If category === 'uncategorized' remove it.
				if ($category->name === 'Uncategorized') {
					try {
						wp_remove_object_terms($product, $category->term_id, 'product_cat');
						$geslibApi->biblio_debug_log('INFO : GeslibApi removeUncategorizedCategory', "We removed Uncategorized category from product $product");
					} catch (Exception $e) {
						$geslibApi->biblio_debug_log('ERROR : GeslibApi removeUncategorizedCategory', "We could not remove Uncategorized category from product $product");
					}
				}
			}
		}	
	}
}