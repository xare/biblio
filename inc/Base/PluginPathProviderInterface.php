<?php
/**
 * @package biblio
 */

namespace Inc\Biblio\Base;

/**
 * Interface for Plugin Path Provider
 *
 * Abstracts the source of plugin paths for easier testing.
 */
interface PluginPathProviderInterface
{
    /**
     * Get the plugin directory path.
     *
     * @return string
     */
    public function getPluginPath(): string;

    /**
     * Get the plugin URL.
     *
     * @return string
     */
    public function getPluginUrl(): string;

    /**
     * Get the plugin templates directory path.
     *
     * @return string
     */
    public function getTemplatesPath(): string;

    /**
     * Get the plugin basename.
     *
     * @return string
     */
    public function getPlugin(): string;
}
