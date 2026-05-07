<?php
/**
 * @package biblio
 */

namespace Inc\Biblio\Base;

/**
 * WordPress Plugin Path Provider
 *
 * Provides plugin paths using WordPress functions.
 * Can be replaced with a test implementation for unit testing.
 */
class WordPressPluginPathProvider implements PluginPathProviderInterface
{
    /**
     * Plugin main file path
     *
     * @var string
     */
    private string $plugin_file;

    /**
     * Constructor
     *
     * @param string $plugin_file The main plugin file path.
     */
    public function __construct(string $plugin_file)
    {
        $this->plugin_file = $plugin_file;
    }

    /**
     * Get the plugin directory path.
     *
     * @return string
     */
    public function getPluginPath(): string
    {
        return \plugin_dir_path($this->plugin_file);
    }

    /**
     * Get the plugin URL.
     *
     * @return string
     */
    public function getPluginUrl(): string
    {
        return \plugin_dir_url($this->plugin_file);
    }

    /**
     * Get the plugin templates directory path.
     *
     * @return string
     */
    public function getTemplatesPath(): string
    {
        return $this->getPluginPath() . 'templates';
    }

    /**
     * Get the plugin basename.
     *
     * @return string
     */
    public function getPlugin(): string
    {
        return \plugin_basename($this->plugin_file);
    }
}
