<?php
namespace Inc\Biblio\Base;

use Inc\Biblio\Base\BaseController;
use Inc\Biblio\Api\SettingsApi;
use Inc\Biblio\Api\Callbacks\AdminCallbacks;
use Inc\Biblio\Api\Callbacks\TaxonomyCallbacks;

class CustomTaxonomyController extends BaseController
{
  public $settings;
  public $callbacks;
  public $taxonomy_callbacks;
  public $subpages = [];
  public $taxonomies = [];

  public function register()
  {
    if( !$this->activated('taxonomy_manager')) return;

    $this->settings = new SettingsApi();

    $this->setSubpages();
    $this->callbacks = new AdminCallbacks();
    $this->taxonomy_callbacks = new TaxonomyCallbacks();
    $this->setSubpages();
		$this->setSettings();
		$this->setSections();
		$this->setFields();

    $this->settings->addSubPages($this->subpages)->register();
    $this->storeCustomTaxonomies();

    if ( ! empty( $this->taxonomies ) ) {
			add_action( 'init', array( $this, 'registerCustomTaxonomy' ));
		}
  }

  public function setSubpages(){
		$this->subpages = [
			[
				'parent_slug' => 'biblio',
				'page_title' => 'Taxonomy Types',
				'menu_title' => 'Taxonomy',
				'capability' => 'manage_options',
				'menu_slug' => 'biblio_taxonomy',
				'callback' => [$this->callbacks, 'adminTaxonomy'] ,
			]
		];
	}

  public function setSettings()
	{
    $args = [
      [
        'option_group' => 'biblio_taxonomy_settings',
        'option_name' => 'biblio_taxonomy',
        'callback' => [$this->taxonomy_callbacks, 'taxonomySanitize']
      ]
    ];
    $this->settings->setSettings($args);
  }

  public function setSections()
	{
    $args = [
      [
        'id' => 'biblio_taxonomy_index',
        'title' => 'Custom Taxonomy Manager',
        'callback' => [$this->taxonomy_callbacks, 'taxonomySectionManager'],
        'page' => 'biblio_taxonomy'
      ]
    ];
    $this->settings->setSections( $args );
  }

  public function setFields()
	{
    $args = [
      [
        'id' => 'taxonomy',
        'title' => 'Custom Taxonomy ID',
        'callback' => [$this->taxonomy_callbacks, 'textField'],
        'page' => 'biblio_taxonomy',
        'section' => 'biblio_taxonomy_index',
        'args' => [
          'option_name' => 'biblio_taxonomy',
          'label_for' => 'taxonomy',
          'placeholder' => 'eg. genre',
          'array' => 'taxonomy'
        ]
      ],
      [
        'id' => 'singular_name',
        'title' => 'Singular Name',
        'callback' => [$this->taxonomy_callbacks, 'textField'],
        'page' => 'biblio_taxonomy',
        'section' => 'biblio_taxonomy_index',
        'args' => [
          'option_name' => 'biblio_taxonomy',
          'label_for' => 'singular_name',
          'placeholder' => 'eg. genre',
          'array' => 'taxonomy'
        ]
      ],
      [
        'id'=> 'hierarchical',
        'title' => 'Hierarchical',
        'callback' => [$this->taxonomy_callbacks, 'checkboxField'],
        'page' => 'biblio_taxonomy', //From menu_slug
        'section' => 'biblio_taxonomy_index',
        'args' => [
            'option_name' => 'biblio_taxonomy',
            'label_for' => 'hierarchical',
            'class' => 'ui-toggle',
            'array' => 'taxonomy'
          ]
        ],
        [
          'id' => 'objects',
          'title' => 'Post Types',
          'callback' => [ $this->taxonomy_callbacks, 'checkboxPostTypesField' ],
          'page' => 'biblio_taxonomy',
          'section' => 'biblio_taxonomy_index',
          'args' => [
            'option_name' => 'biblio_taxonomy',
            'label_for' => 'objects',
            'class' => 'ui-toggle',
            'array' => 'taxonomy'
          ]
        ]
      ];
    $this->settings->setFields( $args );
  }

  public function storeCustomTaxonomies()
  {
    //get the taxonomies array
    $options = get_option( 'biblio_taxonomy' ) ?: [];
    //store infor in an array
    foreach($options as $option ) {
      $labels = [
				'name'              => $option['singular_name'],
				'singular_name'     => $option['singular_name'],
				'search_items'      => 'Search ' . $option['singular_name'],
				'all_items'         => 'All ' . $option['singular_name'],
				'parent_item'       => 'Parent ' . $option['singular_name'],
				'parent_item_colon' => 'Parent ' . $option['singular_name'] . ':',
				'edit_item'         => 'Edit ' . $option['singular_name'],
				'update_item'       => 'Update ' . $option['singular_name'],
				'add_new_item'      => 'Add New ' . $option['singular_name'],
				'new_item_name'     => 'New ' . $option['singular_name'] . ' Name',
				'menu_name'         => $option['singular_name'],
      ];
      $this->taxonomies[] = [
        'hierarchical'      => isset($option['hierarchical']) ? true : false,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_admin_column' => true,
				'query_var'         => true,
        'show_in_rest'      => true,
				'rewrite'           => [ 'slug' => $option['taxonomy'] ],
        'objects'           => isset($option['objects']) ? $option['objects'] : null
      ];
      // register the taxonomy
    }
  }

  public function registerCustomTaxonomy()
	{
		foreach ($this->taxonomies as $taxonomy) {
      $objects = isset($taxonomy['objects']) ? array_keys($taxonomy['objects']) : null;
			register_taxonomy( $taxonomy['rewrite']['slug'], $objects, $taxonomy );
		}
	}
}