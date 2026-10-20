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
     * Test debug method writes the single-line format
     *
     * @return void
     */
    public function testDebugMethodWritesCorrectContent(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->debug('TestTitle', 'Test message');

        $log_file = $this->temp_dir . '/debug-biblio-' . date('Y-m-d') . '.log';
        $content = file_get_contents($log_file);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[DEBUG\] \[biblio\] TestTitle: Test message$/',
            trim($content)
        );
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

    /**
     * Test level, type and title placement on the single log line
     *
     * @return void
     */
    public function testLevelTypeAndTitlePlacement(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->error(
            'Inc\Covers\Api\CoversApi:256 fetch_cover',
            'Connection exception: cURL error 28',
            'covers'
        );

        $log_file = $this->temp_dir . '/debug-covers-' . date('Y-m-d') . '.log';
        $content = file_get_contents($log_file);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[ERROR\] \[covers\] '
            . preg_quote('Inc\Covers\Api\CoversApi:256 fetch_cover', '/')
            . ': '
            . preg_quote('Connection exception: cURL error 28', '/')
            . '$/',
            trim($content)
        );
    }

    /**
     * Test a multiline message collapses into a single physical line
     *
     * @return void
     */
    public function testMultilineMessageCollapsesToPipes(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->info('Multi', "first line\nsecond line\r\nthird line", 'covers');

        $log_file = $this->temp_dir . '/debug-covers-' . date('Y-m-d') . '.log';
        $content = file_get_contents($log_file);

        $this->assertStringContainsString(
            'first line | second line | third line',
            $content
        );
        $lines = explode("\n", trim($content));
        $this->assertCount(1, $lines);
    }

    /**
     * Test an empty title produces no "title:" segment
     *
     * @return void
     */
    public function testEmptyTitleOmitsTitleSegment(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->info('', 'Something happened', 'covers');

        $log_file = $this->temp_dir . '/debug-covers-' . date('Y-m-d') . '.log';
        $content = trim(file_get_contents($log_file));

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2} \[INFO\] \[covers\] Something happened$/',
            $content
        );
        $this->assertStringNotContainsString('] :', $content);
    }

    /**
     * Test messages over the cap are truncated with the marker
     *
     * @return void
     */
    public function testLongMessageIsTruncatedWithMarker(): void
    {
        $logger = new FileLogger($this->temp_dir);
        $logger->debug('CapTitle', str_repeat('A', 3000));

        $log_file = $this->temp_dir . '/debug-biblio-' . date('Y-m-d') . '.log';
        $line = trim(file_get_contents($log_file));

        $this->assertStringEndsWith(' ... [truncated, 3000 bytes]', $line);
        // The cut keeps exactly 2000 bytes of the sanitized message.
        $this->assertMatchesRegularExpression(
            '/\[DEBUG\] \[biblio\] CapTitle: A{2000}$/',
            substr($line, 0, -strlen(' ... [truncated, 3000 bytes]'))
        );
    }
}
