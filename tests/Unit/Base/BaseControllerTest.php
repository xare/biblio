<?php
/**
 * Tests for BaseController
 */

namespace Tests\Unit\Base;

use Inc\Biblio\Base\BaseController;
use Inc\Biblio\Base\PluginPathProviderInterface;
use Tests\UnitTestCase;
use Mockery;

class BaseControllerTest extends UnitTestCase
{
    /**
     * Mock path provider for testing
     *
     * @var PluginPathProviderInterface
     */
    private $mock_path_provider;

    /**
     * Set up test fixtures
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock path provider
        $this->mock_path_provider = Mockery::mock(PluginPathProviderInterface::class);
        $this->mock_path_provider->shouldReceive('getPluginPath')
            ->andReturn('/var/www/html/wp-content/plugins/biblio/');
        $this->mock_path_provider->shouldReceive('getPluginUrl')
            ->andReturn('http://example.com/wp-content/plugins/biblio/');
        $this->mock_path_provider->shouldReceive('getTemplatesPath')
            ->andReturn('/var/www/html/wp-content/plugins/biblio/templates');
        $this->mock_path_provider->shouldReceive('getPlugin')
            ->andReturn('biblio/biblio.php');
    }

    /**
     * Test BaseController initializes with provided path provider
     *
     * @return void
     */
    public function testInitializesWithProvidedPathProvider(): void
    {
        $controller = new BaseController($this->mock_path_provider);

        $this->assertEquals('/var/www/html/wp-content/plugins/biblio/', $controller->plugin_path);
        $this->assertEquals('http://example.com/wp-content/plugins/biblio/', $controller->plugin_url);
        $this->assertEquals('/var/www/html/wp-content/plugins/biblio/templates', $controller->plugin_templates_path);
        $this->assertEquals('biblio/biblio.php', $controller->plugin);
    }

    /**
     * Test BaseController initializes managers array
     *
     * @return void
     */
    public function testInitializesManagersArray(): void
    {
        $controller = new BaseController($this->mock_path_provider);

        $this->assertIsArray($controller->managers);
        $this->assertArrayHasKey('cpt_manager', $controller->managers);
        $this->assertArrayHasKey('taxonomy_manager', $controller->managers);
    }

    /**
     * Test setPathProvider updates the provider
     *
     * @return void
     */
    public function testSetPathProviderUpdatesProvider(): void
    {
        $controller = new BaseController($this->mock_path_provider);

        $new_provider = Mockery::mock(PluginPathProviderInterface::class);
        $new_provider->shouldReceive('getPluginPath')
            ->andReturn('/new/path/');

        $controller->setPathProvider($new_provider);

        $this->assertSame($new_provider, $controller->getPathProvider());
    }

    /**
     * Test getPathProvider returns the current provider
     *
     * @return void
     */
    public function testGetPathProviderReturnsCurrentProvider(): void
    {
        $controller = new BaseController($this->mock_path_provider);

        $this->assertSame($this->mock_path_provider, $controller->getPathProvider());
    }
}
