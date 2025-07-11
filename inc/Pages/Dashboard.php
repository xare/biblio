<?php

namespace Inc\Biblio\Pages;

use Inc\Biblio\Api\SettingsApi;
use Inc\Biblio\Base\BaseController;
use Inc\Biblio\Api\Callbacks\AdminCallbacks;
use Inc\Biblio\Api\Callbacks\ManagerCallbacks;

class Dashboard extends BaseController {
    public $settings;
    public $pages = [];
	//public $subpages = []; // Add this line to define subpages
    public $callbacks;
	public $callbacks_mngr;

    public function register() {
        $this->settings = new SettingsApi();
        $this->callbacks = new AdminCallbacks();
		$this->callbacks_mngr = new ManagerCallbacks();
        $this->setPages();
		//$this->setSubpages();
        $this->setSettings();
		$this->setSections();
		$this->setFields();
        $this->settings
			->addPages( $this->pages )
			->withSubPage( 'Dashboard' )
			//->addSubPages( $this->subpages )
			->register();
        /* $this->storeGeslib(); */

    }

    /* public function storeGeslib() {
        $option = get_option('geslib') ?: '';

    } */

    public function setPages(){
		$this->pages = [
			[
				'page_title' => __('Biblio','biblio'),
				'menu_title' =>  __('Biblio','biblio'),
				'capability' => 'manage_options',
				'menu_slug' => 'biblio',
				'callback' => [$this->callbacks, 'adminDashboard'] ,
				'icon_url' => 'dashicons-admin-plugins',
				'position' => 110
			]
		];
	}

	// Define this new method to add your subpages
    /* public function setSubpages() {
        $this->subpages = [
			[
                'parent_slug' => 'biblio', // Parent menu slug
                'page_title' => 'Geslib Files', // Page title
                'menu_title' => 'Geslib Files', // Menu title
                'capability' => 'manage_options', // Capability
                'menu_slug' => 'geslib_files', // Menu slug
                'callback' => [$this->callbacks, 'adminFilesTable'] // Callback function, define it in AdminCallbacks class
			],
			[
                'parent_slug' => 'bibio', // Parent menu slug
                'page_title' => 'Geslib Log', // Page title
                'menu_title' => 'Geslib Log', // Menu title
                'capability' => 'manage_options', // Capability
                'menu_slug' => 'geslib_log', // Menu slug
                'callback' => [$this->callbacks, 'adminLogTable'] // Callback function, define it in AdminCallbacks class
			],
			[
                'parent_slug' => 'biblio', // Parent menu slug
                'page_title' => 'Geslib Queues', // Page title
                'menu_title' => 'Geslib Queues', // Menu title
                'capability' => 'manage_options', // Capability
                'menu_slug' => 'geslib_queues', // Menu slug
                'callback' => [$this->callbacks, 'adminQueuesTable'] // Callback function, define it in AdminCallbacks class
			],
        ];
    } */

    public function setSettings()
	{
		$args = [
			[
				'option_group'=> 'biblio_options_group',
				'option_name' => 'biblio_settings',
				'callback' => [$this->callbacks_mngr, 'checkboxSanitize']
            ]
		];

		$this->settings->setSettings( $args );

	}

    public function setSections()
	{
		$args = [
			[
				'id'=> 'biblio_admin_index',
				'title' => 'Settings Manager',
				'callback' => [$this->callbacks_mngr , 'adminSectionManager'],
				'page' => 'biblio' //From menu_slug
				]
		];
		$this->settings->setSections( $args );
	}

    public function setFields()
	{
		$args = [];
		foreach($this->managers as $key => $value) {
			$args[] = [
				'id'=> $key,
				'title' => $value,
				'callback' => [$this->callbacks_mngr, 'checkboxField'],
				'page' => 'biblio', //From menu_slug
				'section' => 'biblio_admin_index',
				'args' => [
						'option_name' => 'biblio_settings',
						'label_for' => $key,
						'class' => 'ui-toggle'
					]
			];
		}
		$this->settings->setFields( $args );
	}
}