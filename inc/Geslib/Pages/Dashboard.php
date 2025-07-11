<?php

namespace Inc\Geslib\Pages;

use Inc\Geslib\Api\SettingsApi;
use Inc\Geslib\Base\BaseController;
use Inc\Geslib\Api\Callbacks\AdminCallbacks;
use Inc\Geslib\Api\Callbacks\ManagerCallbacks;

class Dashboard extends BaseController {
    public $settings;
    public $pages = [];
	public $subpages = []; // Add this line to define subpages
    public $callbacks;
	public $callbacks_mngr;

    public function register() {
        $this->settings = new SettingsApi();
        $this->callbacks = new AdminCallbacks();
		$this->callbacks_mngr = new ManagerCallbacks();
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
        /* $this->storeGeslib(); */

    }

    /* public function storeGeslib() {
        $option = get_option('geslib') ?: '';

    } */

    public function setPages(){
		$this->pages = [
			[
				'page_title' => __('Geslib','geslib'),
				'menu_title' =>  __('Geslib','geslib'),
				'capability' => 'manage_options',
				'menu_slug' => 'geslib',
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
                'parent_slug' => 'geslib', // Parent menu slug
                'page_title' => 'Geslib Files', // Page title
                'menu_title' => 'Geslib Files', // Menu title
                'capability' => 'manage_options', // Capability
                'menu_slug' => 'geslib_files', // Menu slug
                'callback' => [$this->callbacks, 'adminFilesTable'] // Callback function, define it in AdminCallbacks class
			],
			[
                'parent_slug' => 'geslib', // Parent menu slug
                'page_title' => 'Geslib Log', // Page title
                'menu_title' => 'Geslib Log', // Menu title
                'capability' => 'manage_options', // Capability
                'menu_slug' => 'geslib_log', // Menu slug
                'callback' => [$this->callbacks, 'adminLogTable'] // Callback function, define it in AdminCallbacks class
			],
			[
                'parent_slug' => 'geslib', // Parent menu slug
                'page_title' => 'Geslib Queues', // Page title
                'menu_title' => 'Geslib Queues', // Menu title
                'capability' => 'manage_options', // Capability
                'menu_slug' => 'geslib_queues', // Menu slug
                'callback' => [$this->callbacks, 'adminQueuesTable'] // Callback function, define it in AdminCallbacks class
			],
        ];
    }

    public function setSettings()
	{
		$default_settings = [
			'geslib_folder_index' => 'geslib'
		];
		/* foreach ($this->managers as $key => $label) {
			$default_settings[$key] = 0;
		} */
		$args = [
			[
				'option_group'=> 'geslib_settings',
				'option_name' => 'geslib_settings',
				'callback' => [$this->callbacks, 'textSanitize']
            ]
		];

		$this->settings->setSettings( $args );

		// Save the default option if it doesn't exist
		// Ensure it's an array before merging
		$saved_settings = get_option('geslib_settings');
		if (!is_array($saved_settings)) {
			$saved_settings = [];
		}
        
        if (!$saved_settings) {
            update_option('geslib_settings', $default_settings);
        } else {
            // Ensure all default keys exist
            $new_settings = array_merge($default_settings, $saved_settings);
            update_option('geslib_settings', $new_settings);
        }
	}

    public function setSections()
	{
		$args = [
			[
				'id'=> 'geslib_admin_index',
				'title' => 'Settings Manager',
				'callback' => [$this->callbacks , 'adminSectionManager'],
				'page' => 'geslib' //From menu_slug
				]
		];
		$this->settings->setSections( $args );
	}

    public function setFields()
	{
		//$line_types = $this->callbacks_mngr::getLineTypes();
		$args = [
                    [
						'id'=> 'geslib_folder_index',
						'title' => 'Geslib folder Name',
						'callback' => [$this->callbacks, 'textField'],
						'page' => 'geslib', //From menu_slug
						'section' => 'geslib_admin_index',
						'args' => [
								'option_name' => 'geslib_settings',
								'label_for' => 'geslib_folder_index',
								'class' => 'regular-text'
							]
		            ]
                ];
		/* foreach ($line_types as $key => $label) {
			$args[] = [
				'id' => $key,
				'title' => '<em>['.$key.']</em> ' . $label,
				'callback' => [$this->callbacks_mngr, 'checkboxField'],
				'page' => 'geslib',
				'section' => 'geslib_admin_index',
				'args' => [
					'option_name' => 'geslib_settings',
					'label_for' => $key,
					'class' => 'ui-toggle'
				]
			];
		} */
		$this->settings->setFields( $args );
	}
}