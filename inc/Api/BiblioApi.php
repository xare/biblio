<?php
namespace Inc\Biblio\Api;

/**
 * Biblio API
 *
 * Provides utility methods for the Biblio plugin.
 *
 * @deprecated Use LoggerInterface implementations instead of debug_log method.
 */
class BiblioApi {
    /**
     * Logger instance
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param LoggerInterface|null $logger Optional logger instance.
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        if ($logger === null) {
            $logs_dir = ABSPATH . 'wp-content/plugins/biblio/logs';
            $logger = new FileLogger($logs_dir);
        }
        $this->logger = $logger;
    }

    /**
     * Debug log (legacy method for backwards compatibility)
     *
     * @param string $title      The log title.
     * @param string $message    The message to log.
     * @param string $plugintype The plugin type (default: 'biblio').
     *
     * @return void
     *
     * @deprecated Use the logger instance directly instead.
     */
    public function debug_log(string $title, string $message, string $plugintype = 'biblio'): void
    {
        $this->logger->debug($title, $message, $plugintype);
    }

    /**
     * Get the logger instance
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Set the logger instance
     *
     * @param LoggerInterface $logger The logger instance.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}