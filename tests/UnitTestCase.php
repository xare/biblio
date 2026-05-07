<?php
/**
 * Base Test Case for Unit Tests
 *
 * Provides common setup and utilities for all unit tests.
 */

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mockery;

abstract class UnitTestCase extends TestCase
{
    /**
     * Tear down test environment after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock for WordPress get_option function
     *
     * @param string $option_name The option name.
     * @param mixed  $value       The option value.
     *
     * @return array Mock data for testing
     */
    protected function mockOptionData(string $option_name, $value): array
    {
        return [
            $option_name => $value,
        ];
    }

    /**
     * Plugin paths for testing.
     *
     * @return array
     */
    protected function getTestPluginPaths(): array
    {
        return [
            'plugin_path' => dirname(__FILE__, 2),
            'plugin_url' => 'http://example.com/wp-content/plugins/biblio/',
            'plugin_templates_path' => dirname(__FILE__, 2) . '/templates',
            'plugin' => 'biblio/biblio.php',
        ];
    }
}
