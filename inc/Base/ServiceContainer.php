<?php
/**
 * @package biblio
 */

namespace Inc\Biblio\Base;

use Closure;

/**
 * Simple Service Container
 *
 * A lightweight dependency injection container for managing plugin services.
 */
class ServiceContainer
{
    /**
     * Registered services
     *
     * @var array
     */
    private array $services = [];

    /**
     * Service instances cache
     *
     * @var array
     */
    private array $instances = [];

    /**
     * Register a service factory
     *
     * @param string $key     The service key.
     * @param Closure|string $factory The service factory or class name.
     *
     * @return void
     */
    public function register(string $key, $factory): void
    {
        $this->services[$key] = $factory;
        // Clear cached instance if reregistering
        unset($this->instances[$key]);
    }

    /**
     * Register a singleton service
     *
     * @param string $key     The service key.
     * @param mixed  $instance The service instance.
     *
     * @return void
     */
    public function singleton(string $key, $instance): void
    {
        $this->instances[$key] = $instance;
    }

    /**
     * Resolve a service
     *
     * @param string $key The service key.
     *
     * @return mixed
     *
     * @throws \RuntimeException If service is not registered.
     */
    public function resolve(string $key)
    {
        // Return cached instance if available
        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        // Check if service is registered
        if (!isset($this->services[$key])) {
            throw new \RuntimeException("Service '$key' is not registered in the container.");
        }

        $factory = $this->services[$key];

        // If factory is a Closure, call it
        if ($factory instanceof Closure) {
            return $factory($this);
        }

        // If factory is a string, assume it's a class name and instantiate it
        if (is_string($factory) && class_exists($factory)) {
            return new $factory();
        }

        throw new \RuntimeException("Service '$key' could not be resolved.");
    }

    /**
     * Check if a service is registered
     *
     * @param string $key The service key.
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->services[$key]) || isset($this->instances[$key]);
    }

    /**
     * Get all registered service keys
     *
     * @return array
     */
    public function getRegisteredServices(): array
    {
        return array_keys(array_merge($this->services, $this->instances));
    }

    /**
     * Clear all services and instances
     *
     * @return void
     */
    public function clear(): void
    {
        $this->services = [];
        $this->instances = [];
    }

    /**
     * Get a service by class name (creates singleton if not exists)
     *
     * @param string $className The class name of the service.
     *
     * @return mixed
     */
    public function get(string $className)
    {
        $key = $className;

        if (isset($this->instances[$key])) {
            return $this->instances[$key];
        }

        if (class_exists($className)) {
            $this->instances[$key] = new $className();
            return $this->instances[$key];
        }

        throw new \RuntimeException("Class '$className' does not exist.");
    }
}
