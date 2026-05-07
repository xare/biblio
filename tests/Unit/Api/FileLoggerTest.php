<?php
/**
 * Tests for FileLogger
 */

namespace Tests\Unit\Api;

use Inc\Biblio\Api\FileLogger;
use Tests\UnitTestCase;

class FileLoggerTest extends UnitTestCase
{
    /**
     * Temporary directory for test files
     *
     * @var string
     */
    private string $temp_dir;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory for logs
        $this->temp_dir = sys_get_temp_dir() . '/biblio-test-logs-' . uniqid();
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }

    /**
     * Tear down test fixtures
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->temp_dir)) {
            array_map('unlink', glob("$this->temp_dir/*.*"));
            rmdir($this->temp_dir);
        }

        parent::tearDown();
    }

    /**
     * Test debug method creates log file
     *
     * @return void
     */
    public function testDebugMethodCreatesLogFile(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->debug('TestTitle', 'Test message');

        $log_file = $this->temp_dir . '/debug-biblio-' . date('Y-m-d') . '.log';
        $this->assertFileExists($log_file);
    }

    /**
     * Test debug method writes correct content
     *
     * @return void
     */
    public function testDebugMethodWritesCorrectContent(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->debug('TestTitle', 'Test message');

        $log_file = $this->temp_dir . '/debug-biblio-' . date('Y-m-d') . '.log';
        $content = file_get_contents($log_file);

        $this->assertStringContainsString('DEBUG', $content);
        $this->assertStringContainsString('TestTitle', $content);
        $this->assertStringContainsString('Test message', $content);
    }

    /**
     * Test error method creates error log
     *
     * @return void
     */
    public function testErrorMethodCreatesErrorLog(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->error('ErrorTitle', 'Error message');

        $log_file = $this->temp_dir . '/debug-biblio-' . date('Y-m-d') . '.log';
        $content = file_get_contents($log_file);

        $this->assertStringContainsString('ERROR', $content);
        $this->assertStringContainsString('ErrorTitle', $content);
    }

    /**
     * Test info method creates info log
     *
     * @return void
     */
    public function testInfoMethodCreatesInfoLog(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->info('InfoTitle', 'Info message');

        $log_file = $this->temp_dir . '/debug-biblio-' . date('Y-m-d') . '.log';
        $content = file_get_contents($log_file);

        $this->assertStringContainsString('INFO', $content);
        $this->assertStringContainsString('InfoTitle', $content);
    }

    /**
     * Test custom plugin type in log file name
     *
     * @return void
     */
    public function testCustomPluginTypeInLogFileName(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->debug('Test', 'Message', 'geslib');

        $log_file = $this->temp_dir . '/debug-geslib-' . date('Y-m-d') . '.log';
        $this->assertFileExists($log_file);
    }

    /**
     * Test multiple log entries are appended
     *
     * @return void
     */
    public function testMultipleLogEntriesAreAppended(): void
    {
        $logger = new FileLogger($this->temp_dir);
        
        $logger->debug('First', 'First message');
        $logger->debug('Second', 'Second message');

        $log_file = $this->temp_dir . '/debug-biblio-' . date('Y-m-d') . '.log';
        $content = file_get_contents($log_file);

        $this->assertStringContainsString('First', $content);
        $this->assertStringContainsString('Second', $content);
        // Should have two lines
        $lines = count(array_filter(explode("\n", $content)));
        $this->assertGreaterThanOrEqual(2, $lines);
    }
}
