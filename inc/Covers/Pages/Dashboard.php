<?php

namespace Inc\Covers\Pages;

use Inc\Covers\Api\SettingsApi;
use Inc\Covers\Base\BaseController;
use Inc\Covers\Api\Callbacks\AdminCallbacks;

class Dashboard extends BaseController {
    public $settings;
    public $pages = [];
	public $subpages = []; // Add this line to define subpages
    public $callbacks;


    public function register() {
        $this->settings = new SettingsApi();
        $this->callbacks = new AdminCallbacks();
        $this->setPages();
		$this->setSubpages();
        $this->setSettings();
		$this->setSections();
		$this->setFields();
        $this->settings
			->addPages( $this->pages )
			->withSubPage( 'Dashboard' )
			->addSubPages( $this->subpages )
			->register();
        /* $this->storeDilve(); */

    }

    /* public function storeDilve() {
        $option = get_option('Dilve') ?: '';

    } */

    public function setPages(){
		$this->pages = [
			[
				'page_title' => __('Covers','biblio'),
				'menu_title' =>  __('Covers','biblio'),
				'capability' => 'manage_options',
				'menu_slug' => 'covers',
				'callback' => [$this->callbacks, 'adminDashboard'] ,
				'icon_url' => 'dashicons-admin-plugins',
				'position' => 110
			]
		];
	}

	// Define this new method to add your subpages
    public function setSubpages() {
        $this->subpages = [
			[
                'parent_slug' => 'covers', // Parent menu slug
                'page_title' => 'Dilve Logs', // Page title
                'menu_title' => 'Dilve Logs', // Menu title
                'capability' => 'manage_options', // Capability
                'menu_slug' => 'dilve_logs', // Menu slug
                'callback' => [$this->callbacks, 'adminDilveLogs'], // Callback function, define it in AdminCallbacks class
            ],
            [
                'parent_slug' => 'covers', // Parent menu slug
                'page_title' => 'Dilve Logger', // Page title
                'menu_title' => 'Dilve Logger', // Menu title
                'capability' => 'manage_options', // Capability
                'menu_slug' => 'dilve_logger', // Menu slug
                'callback' => [$this->callbacks, 'adminDilveLogger'], // Callback function, define it in AdminCallbacks class
			],
			[
                'parent_slug' => 'covers', // Parent menu slug
                'page_title' => 'Dilve Lines', // Page title
                'menu_title' => 'Dilve Lines', // Menu title
                'capability' => 'manage_options', // Capability
                'menu_slug' => 'dilve_lines', // Menu slug
                'callback' => [$this->callbacks, 'adminDilveLines'], // Callback function, define it in AdminCallbacks class
			],
        ];
    }
    public function setSettings()
	{
		$args = [
			[
				'option_group'=> 'covers_settings',
				'option_name' => 'covers_settings',
				'callback' => [$this->callbacks, 'textSanitize']
            ]
		];

		$this->settings->setSettings( $args );

		// Save the default option if it doesn't exist
		if ( !get_option('covers_settings') ) {
			$default_settings = [
				'dilve_user' => '',
				'cegal_user' => '',
			];
			update_option('covers_settings', $default_settings);
		}
	}

    public function setSections()
	{
		$args = [
					[
						'id'=> 'covers_admin_index',
						'title' => 'Settings Manager',
						'callback' => [$this->callbacks , 'adminSectionManager'],
						'page' => 'covers' //From menu_slug
					]
		];
		$this->settings->setSections( $args );
	}

    public function setFields()
	{
		$args = [
                    [
						'id'=> 'dilve_user',
						'title' => 'Dilve User Name',
						'callback' => [$this->callbacks, 'textField'],
						'page' => 'covers', //From menu_slug
						'section' => 'covers_admin_index',
						'args' => [
							'option_name' => 'covers_settings',
							'label_for' => 'dilve_user',
							'class' => 'regular-text'
						]
					],
					[
						'id'=> 'dilve_pass',
						'title' => 'Dilve Password',
						'callback' => [$this->callbacks, 'textField'],
						'page' => 'covers', //From menu_slug
						'section' => 'covers_admin_index',
						'args' => [
							'option_name' => 'covers_settings',
							'label_for' => 'dilve_pass',
							'class' => 'regular-text'
						]
					],
					[
						'id'=> 'cegal_user',
						'title' => 'Cegal User Name',
						'callback' => [$this->callbacks, 'textField'],
						'page' => 'covers', //From menu_slug
						'section' => 'covers_admin_index',
						'args' => [
							'option_name' => 'covers_settings',
							'label_for' => 'cegal_user',
							'class' => 'regular-text'
						]
					],
					[
						'id'=> 'cegal_pass',
						'title' => 'Cegal Password',
						'callback' => [$this->callbacks, 'textField'],
						'page' => 'covers', //From menu_slug
						'section' => 'covers_admin_index',
						'args' => [
							'option_name' => 'covers_settings',
							'label_for' => 'cegal_pass',
							'class' => 'regular-text'
						]
					]
                ];
		$this->settings->setFields( $args );
	}
}