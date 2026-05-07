<?php
/**
 * @package biblio
 */

namespace Inc\Biblio\Api;

/**
 * Logger Interface
 *
 * Defines the contract for logging implementations.
 */
interface LoggerInterface
{
    /**
     * Log a debug message
     *
     * @param string $title    The log title/category.
     * @param string $message  The log message.
     * @param string $type     The plugin type (biblio, geslib, covers, etc.).
     *
     * @return void
     */
    public function debug(string $title, string $message, string $type = 'biblio'): void;

    /**
     * Log an error message
     *
     * @param string $title    The log title/category.
     * @param string $message  The error message.
     * @param string $type     The plugin type.
     *
     * @return void
     */
    public function error(string $title, string $message, string $type = 'biblio'): void;

    /**
     * Log an info message
     *
     * @param string $title    The log title/category.
     * @param string $message  The info message.
     * @param string $type     The plugin type.
     *
     * @return void
     */
    public function info(string $title, string $message, string $type = 'biblio'): void;
}
