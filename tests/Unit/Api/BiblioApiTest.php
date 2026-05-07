<?php
/**
 * Tests for BiblioApi
 */

namespace Tests\Unit\Api;

use Inc\Biblio\Api\BiblioApi;
use Inc\Biblio\Api\LoggerInterface;
use Tests\UnitTestCase;
use Mockery;

class BiblioApiTest extends UnitTestCase
{
    /**
     * Test BiblioApi initializes with custom logger
     *
     * @return void
     */
    public function testInitializesWithCustomLogger(): void
    {
        $mock_logger = Mockery::mock(LoggerInterface::class);
        $api = new BiblioApi($mock_logger);

        $this->assertSame($mock_logger, $api->getLogger());
    }

    /**
     * Test debug_log calls logger debug method
     *
     * @return void
     */
    public function testDebugLogCallsLoggerDebugMethod(): void
    {
        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('debug')
            ->once()
            ->with('TestTitle', 'Test message', 'biblio');

        $api = new BiblioApi($mock_logger);
        $api->debug_log('TestTitle', 'Test message');
        
        // Verify Mockery expectations
        $mock_logger->mockery_verify();
        $this->assertTrue(true);
    }

    /**
     * Test debug_log with custom plugin type
     *
     * @return void
     */
    public function testDebugLogWithCustomPluginType(): void
    {
        $mock_logger = Mockery::mock(LoggerInterface::class);
        $mock_logger->shouldReceive('debug')
            ->once()
            ->with('Title', 'Message', 'geslib');

        $api = new BiblioApi($mock_logger);
        $api->debug_log('Title', 'Message', 'geslib');
        
        // Verify Mockery expectations
        $mock_logger->mockery_verify();
        $this->assertTrue(true);
    }

    /**
     * Test setLogger updates the logger instance
     *
     * @return void
     */
    public function testSetLoggerUpdatesInstance(): void
    {
        $logger1 = Mockery::mock(LoggerInterface::class);
        $logger2 = Mockery::mock(LoggerInterface::class);

        $api = new BiblioApi($logger1);
        $this->assertSame($logger1, $api->getLogger());

        $api->setLogger($logger2);
        $this->assertSame($logger2, $api->getLogger());
    }

    /**
     * Test getLogger returns the current logger
     *
     * @return void
     */
    public function testGetLoggerReturnsCurrent(): void
    {
        $mock_logger = Mockery::mock(LoggerInterface::class);
        $api = new BiblioApi($mock_logger);

        $this->assertSame($mock_logger, $api->getLogger());
    }
}
