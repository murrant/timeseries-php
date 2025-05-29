<?php

namespace TimeSeriesPhp\Core\Factory;

use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

/**
 * Driver Manager for TimeSeriesPhp
 * 
 * This class provides a more intuitive API for managing drivers.
 * It is a wrapper around TSDBFactory that provides a more object-oriented approach.
 */
class DriverManager
{
    /**
     * Create a new instance of a time series database driver
     * 
     * @param  string  $driver  The name of the driver to create
     * @param  ConfigInterface|null  $config  The configuration for the driver (optional)
     * @param  bool  $autoConnect  Whether to automatically connect to the database
     * @return TimeSeriesInterface A new instance of the driver
     * 
     * @throws DriverException If the driver is not registered or doesn't implement TimeSeriesInterface
     */
    public function create(string $driver, ?ConfigInterface $config = null, bool $autoConnect = true): TimeSeriesInterface
    {
        return TSDBFactory::create($driver, $config, $autoConnect);
    }

    /**
     * Register a driver with the factory
     * 
     * @param  string  $name  The name of the driver
     * @param  class-string  $className  The fully qualified class name of the driver
     * @param  class-string|null  $configClassName  The fully qualified class name of the config class (optional)
     * 
     * @throws DriverException If the class doesn't exist or doesn't implement TimeSeriesInterface
     */
    public function registerDriver(string $name, string $className, ?string $configClassName = null): void
    {
        TSDBFactory::registerDriver($name, $className, $configClassName);
    }

    /**
     * Unregister a driver from the factory
     * 
     * @param  string  $name  The name of the driver to unregister
     * @return bool True if the driver was unregistered, false if it wasn't registered
     */
    public function unregisterDriver(string $name): bool
    {
        return TSDBFactory::unregisterDriver($name);
    }

    /**
     * Get a list of all registered drivers
     * 
     * @return string[] Array of driver names
     */
    public function getAvailableDrivers(): array
    {
        return TSDBFactory::getAvailableDrivers();
    }

    /**
     * Check if a driver is registered
     * 
     * @param  string  $name  The name of the driver to check
     * @return bool True if the driver is registered, false otherwise
     */
    public function hasDriver(string $name): bool
    {
        return TSDBFactory::hasDriver($name);
    }

    /**
     * Get the config class for a driver
     * 
     * @param  string  $name  The name of the driver
     * @return class-string|null The fully qualified class name of the config class, or null if the driver is not registered
     */
    public function getConfigClass(string $name): ?string
    {
        return TSDBFactory::getConfigClass($name);
    }

    /**
     * Create a configuration instance for a driver
     * 
     * @param  string  $driver  The name of the driver
     * @param  array<string, mixed>  $config  The configuration options
     * @return ConfigInterface A new instance of the driver's configuration class
     * 
     * @throws DriverException If the driver is not registered or the config class is invalid
     */
    public function createConfig(string $driver, array $config = []): ConfigInterface
    {
        return TSDBFactory::createConfig($driver, $config);
    }
}
