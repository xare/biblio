<?php
/**
 * @package biblio
 */

namespace Inc\Biblio\Api;

/**
 * File-based Logger
 *
 * Logs messages to files in the plugin logs directory.
 */
class FileLogger implements LoggerInterface
{
    /**
     * The logs directory path
     *
     * @var string
     */
    private string $logs_dir;

    /**
     * Constructor
     *
     * @param string $logs_dir The directory to store log files.
     */
    public function __construct(string $logs_dir)
    {
        $this->logs_dir = rtrim($logs_dir, '/');
        $this->ensureLogsDirectory();
    }

    /**
     * Ensure the logs directory exists
     *
     * @return void
     */
    private function ensureLogsDirectory(): void
    {
        if (!is_dir($this->logs_dir)) {
            wp_mkdir_p($this->logs_dir);
        }
    }

    /**
     * Log a debug message
     *
     * @param string $title    The log title/category.
     * @param string $message  The log message.
     * @param string $type     The plugin type.
     *
     * @return void
     */
    public function debug(string $title, string $message, string $type = 'biblio'): void
    {
        $this->log('DEBUG', $title, $message, $type);
    }

    /**
     * Log an error message
     *
     * @param string $title    The log title/category.
     * @param string $message  The error message.
     * @param string $type     The plugin type.
     *
     * @return void
     */
    public function error(string $title, string $message, string $type = 'biblio'): void
    {
        $this->log('ERROR', $title, $message, $type);
    }

    /**
     * Log an info message
     *
     * @param string $title    The log title/category.
     * @param string $message  The info message.
     * @param string $type     The plugin type.
     *
     * @return void
     */
    public function info(string $title, string $message, string $type = 'biblio'): void
    {
        $this->log('INFO', $title, $message, $type);
    }

    /**
     * Internal log method
     *
     * @param string $level    The log level.
     * @param string $title    The log title/category.
     * @param string $message  The log message.
     * @param string $type     The plugin type.
     *
     * @return void
     */
    private function log(string $level, string $title, string $message, string $type): void
    {
        $filepath = sprintf(
            '%s/debug-%s-%s.log',
            $this->logs_dir,
            $type,
            date('Y-m-d')
        );

        $formatted_message = sprintf(
            '[%s - %s - %s] %s %s %s',
            $level,
            $type,
            $title,
            date('Y-m-d H:i:s'),
            PHP_EOL,
            $message
        );

        file_put_contents($filepath, $formatted_message . PHP_EOL, FILE_APPEND);
    }
}
