<?php
/**
 * @package biblio
 */

namespace Inc\Biblio\Api;

/**
 * Database Guard
 *
 * Detects real $wpdb failures and logs them through the shared logger.
 * wpdb never throws: it returns false/null/[] and populates $wpdb->last_error,
 * so every `try { $wpdb->... } catch (\Exception)` around it is dead code.
 */
final class DatabaseGuard
{
    /**
     * Injected logger override (lets unit tests avoid touching the real logs dir).
     *
     * @var LoggerInterface|null
     */
    private static ?LoggerInterface $logger = null;

    /**
     * Set the logger override
     *
     * @param LoggerInterface|null $logger The logger instance, or null to fall
     *                                     back to BiblioApi::getSharedLogger().
     *
     * @return void
     */
    public static function setLogger(?LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Guard a $wpdb call result against real database failures
     *
     * Caveats (real wpdb behavior):
     * - query() calls flush() before running, so last_error is fresh per query;
     *   but its early-return paths (db not ready, empty query) do NOT flush, so
     *   last_error can be stale in that narrow case.
     * - get_var() returns null and get_results(ARRAY_A) returns [] on failure,
     *   which is why this guard checks last_error and not only the return value.
     *
     * @param mixed  $result  Raw return value from the $wpdb call.
     * @param string $context Human-readable operation description used as the log title.
     * @param string $type    FileLogger routing type; keeps debug-{type}-{date}.log
     *                        separation ('biblio' | 'geslib' | 'covers').
     *
     * @return mixed $result unchanged on success; false on failure.
     */
    public static function guard(mixed $result, string $context, string $type = 'biblio'): mixed
    {
        global $wpdb;

        $last_error = (is_object($wpdb) && isset($wpdb->last_error)) ? (string) $wpdb->last_error : '';

        if (false === $result || '' !== $last_error) {
            $logger = self::$logger ?? BiblioApi::getSharedLogger();
            $message = ('' !== $last_error)
                ? $last_error
                : 'Query returned false without a database error message.';
            $logger->error($context, $message, $type);
            return false;
        }

        return $result;
    }
}
