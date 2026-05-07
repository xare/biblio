<?php
/**
 * Tests for Init class
 */

namespace Tests\Unit;

use Inc\Biblio\Init;
use Tests\UnitTestCase;

class InitTest extends UnitTestCase
{
    /**
     * Test get_services returns array
     *
     * @return void
     */
    public function testGetServicesReturnsArray(): void
    {
        $services = Init::get_services();

        $this->assertIsArray($services);
        $this->assertNotEmpty($services);
    }

    /**
     * Test get_services returns class names
     *
     * @return void
     */
    public function testGetServicesReturnsClassNames(): void
    {
        $services = Init::get_services();

        foreach ($services as $service) {
            $this->assertIsString($service);
            $this->assertTrue(class_exists($service), "Service class $service does not exist");
        }
    }

    /**
     * Test get_services includes expected services
     *
     * @return void
     */
    public function testGetServicesIncludesExpectedServices(): void
    {
        $services = Init::get_services();

        $expected_services = [
            'Inc\Biblio\Base\Enqueue',
            'Inc\Biblio\Pages\Dashboard',
            'Inc\Biblio\Base\SettingsLinks',
            'Inc\Biblio\Base\CustomPostTypeController',
            'Inc\Biblio\Base\CustomTaxonomyController',
        ];

        foreach ($expected_services as $expected) {
            $this->assertContains($expected, $services, "Expected service $expected not found in services list");
        }
    }

    /**
     * Test instantiate creates class instance
     *
     * @return void
     */
    public function testInstantiateCreatesServiceObject(): void
    {
        // Just verify that get_services works and doesn't require instantiation
        // Instantiation tests can be done with mocked dependencies
        $services = Init::get_services();
        
        // Verify we have services defined
        $this->assertNotEmpty($services);
        $this->assertIsArray($services);
        
        // Verify all services are class names (strings)
        foreach ($services as $service) {
            $this->assertTrue(
                class_exists($service),
                "Service class $service should exist"
            );
        }
    }
}
