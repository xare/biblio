<?php
/**
 * Tests for ServiceContainer
 */

namespace Tests\Unit\Base;

use Inc\Biblio\Base\ServiceContainer;
use Tests\UnitTestCase;

class ServiceContainerTest extends UnitTestCase
{
    /**
     * Test register service with factory
     *
     * @return void
     */
    public function testRegisterServiceWithFactory(): void
    {
        $container = new ServiceContainer();
        $container->register('test', function ($c) {
            return new \stdClass();
        });

        $this->assertTrue($container->has('test'));
    }

    /**
     * Test register service with class name
     *
     * @return void
     */
    public function testRegisterServiceWithClassName(): void
    {
        $container = new ServiceContainer();
        $container->register('test', 'stdClass');

        $this->assertTrue($container->has('test'));
        $this->assertInstanceOf('stdClass', $container->resolve('test'));
    }

    /**
     * Test singleton service returns same instance
     *
     * @return void
     */
    public function testSingletonServiceReturnsSameInstance(): void
    {
        $container = new ServiceContainer();
        $instance = new \stdClass();
        $container->singleton('test', $instance);

        $resolved1 = $container->resolve('test');
        $resolved2 = $container->resolve('test');

        $this->assertSame($resolved1, $resolved2);
        $this->assertSame($instance, $resolved1);
    }

    /**
     * Test factory service creates new instances
     *
     * @return void
     */
    public function testFactoryServiceCreatesNewInstances(): void
    {
        $container = new ServiceContainer();
        $container->register('test', function ($c) {
            return new \stdClass();
        });

        $instance1 = $container->resolve('test');
        $instance2 = $container->resolve('test');

        $this->assertNotSame($instance1, $instance2);
    }

    /**
     * Test resolve throws exception for unregistered service
     *
     * @return void
     */
    public function testResolveThrowsExceptionForUnregisteredService(): void
    {
        $this->expectException(\RuntimeException::class);

        $container = new ServiceContainer();
        $container->resolve('nonexistent');
    }

    /**
     * Test has returns false for unregistered service
     *
     * @return void
     */
    public function testHasReturnsFalseForUnregisteredService(): void
    {
        $container = new ServiceContainer();

        $this->assertFalse($container->has('nonexistent'));
    }

    /**
     * Test get registered services
     *
     * @return void
     */
    public function testGetRegisteredServices(): void
    {
        $container = new ServiceContainer();
        $container->register('service1', 'stdClass');
        $container->register('service2', function () { return []; });
        $container->singleton('service3', new \stdClass());

        $services = $container->getRegisteredServices();

        $this->assertContains('service1', $services);
        $this->assertContains('service2', $services);
        $this->assertContains('service3', $services);
    }

    /**
     * Test clear removes all services
     *
     * @return void
     */
    public function testClearRemovesAllServices(): void
    {
        $container = new ServiceContainer();
        $container->register('test', 'stdClass');

        $this->assertTrue($container->has('test'));

        $container->clear();

        $this->assertFalse($container->has('test'));
    }

    /**
     * Test reregistering service updates it
     *
     * @return void
     */
    public function testReregisteringServiceUpdatesIt(): void
    {
        $container = new ServiceContainer();
        $container->register('test', 'stdClass');

        // Create array
        $array_factory = function () { return [1, 2, 3]; };
        $container->register('test', $array_factory);

        $result = $container->resolve('test');
        $this->assertIsArray($result);
        $this->assertEquals([1, 2, 3], $result);
    }

    /**
     * Test factory receives container as argument
     *
     * @return void
     */
    public function testFactoryReceivesContainerAsArgument(): void
    {
        $container = new ServiceContainer();
        $container->register('dependency', function () { return 'dependency_value'; });

        $container->register('service', function ($c) {
            return $c->resolve('dependency');
        });

        $result = $container->resolve('service');
        $this->assertEquals('dependency_value', $result);
    }
}
