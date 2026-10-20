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
     * Maximum number of bytes kept for the message part of one entry.
     * Longer messages are cut and marked with a truncation suffix so a
     * single entry always stays on one physical line.
     */
    private const MAX_MESSAGE_BYTES = 2000;

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
     * Writes ONE physical line per entry:
     * `Y-m-d H:i:s [LEVEL] [type] title: message` (title omitted when empty).
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

        $title   = $this->sanitize($title);
        $message = $this->sanitize($message);

        $message_bytes = strlen($message);
        if ($message_bytes > self::MAX_MESSAGE_BYTES) {
            // mb_strcut cuts at a byte boundary without splitting a
            // multi-byte character; substr is the plain fallback.
            $cut = function_exists('mb_strcut')
                ? mb_strcut($message, 0, self::MAX_MESSAGE_BYTES, 'UTF-8')
                : substr($message, 0, self::MAX_MESSAGE_BYTES);
            if ($cut === '' && $message !== '') {
                $cut = substr($message, 0, self::MAX_MESSAGE_BYTES);
            }
            $message = $cut . ' ... [truncated, ' . $message_bytes . ' bytes]';
        }

        $line = sprintf('%s [%s] [%s]', date('Y-m-d H:i:s'), $level, $type);
        if ($title !== '') {
            $line .= ' ' . $title . ':';
        }
        $line .= ' ' . $message;

        file_put_contents($filepath, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Squeeze a raw string into a single log-safe line: trim it, collapse
     * every run of line breaks into " | " and drop control characters.
     *
     * @param string $value Raw title or message.
     *
     * @return string Sanitized single-line value.
     */
    private function sanitize(string $value): string
    {
        $value = trim($value);
        $value = (string) preg_replace('/\R+/', ' | ', $value);
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
    }
}
