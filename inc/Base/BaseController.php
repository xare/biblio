<?php
/**
 * @package biblio
 */

namespace Inc\Biblio\Base;

/**
 * Base Controller
 *
 * Provides common plugin functionality for all controllers.
 */
class BaseController
{
    /**
     * Plugin path provider instance
     *
     * @var PluginPathProviderInterface
     */
    protected PluginPathProviderInterface $path_provider;

    /**
     * Plugin path
     *
     * @var string
     */
    public $plugin_path;

    /**
     * Plugin URL
     *
     * @var string
     */
    public $plugin_url;

    /**
     * Plugin basename
     *
     * @var string
     */
    public $plugin;

    /**
     * Plugin templates path
     *
     * @var string
     */
    public $plugin_templates_path;

    /**
     * Available managers
     *
     * @var array
     */
    public array $managers;

    /**
     * Constructor
     *
     * @param PluginPathProviderInterface|null $path_provider Optional path provider. Uses WordPress provider if null.
     */
    public function __construct(?PluginPathProviderInterface $path_provider = null)
    {
        if ($path_provider === null) {
            // Use WordPress path provider by default
            $this->path_provider = new WordPressPluginPathProvider(
                dirname(__FILE__, 3) . '/biblio.php'
            );
        } else {
            $this->path_provider = $path_provider;
        }

        $this->plugin_path = $this->path_provider->getPluginPath();
        $this->plugin_templates_path = $this->path_provider->getTemplatesPath();
        $this->plugin_url = $this->path_provider->getPluginUrl();
        $this->plugin = $this->path_provider->getPlugin();

        $this->managers = [
            'cpt_manager' => 'Activate CPT Manager',
            'taxonomy_manager' => 'Activate Taxonomy Manager',
        ];
    }

    /**
     * Check if a manager is activated
     *
     * @param string $key The manager key to check.
     *
     * @return bool
     */
    public function activated(string $key): bool
    {
        $option = \get_option('biblio_settings');
        return isset($option[$key]) ? (bool) $option[$key] : false;
    }

    /**
     * Set the path provider (useful for testing)
     *
     * @param PluginPathProviderInterface $path_provider The path provider instance.
     *
     * @return void
     */
    public function setPathProvider(PluginPathProviderInterface $path_provider): void
    {
        $this->path_provider = $path_provider;
    }

    /**
     * Get the path provider
     *
     * @return PluginPathProviderInterface
     */
    public function getPathProvider(): PluginPathProviderInterface
    {
        return $this->path_provider;
    }
}