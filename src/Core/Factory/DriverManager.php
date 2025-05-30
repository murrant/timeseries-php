<?php

namespace TimeSeriesPhp\Core\Factory;

use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

/**
 * Static facade for TSDBFactory.
 * This class provides backward compatibility for code that uses the static methods.
 */
class DriverManager
{
    private static ?TSDBFactory $instance = null;

    /**
     * Get the factory instance
     */
    private static function getInstance(): TSDBFactory
    {
        if (self::$instance === null) {
            self::$instance = new TSDBFactory;
        }

        return self::$instance;
    }

    /**
     * Reset the factory instance (useful for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Register default drivers
     *
     * @throws DriverException If a driver class doesn't exist or doesn't implement TimeSeriesInterface
     */
    public static function registerDefaultDrivers(): void
    {
        self::getInstance()->registerDefaultDrivers();
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
    public static function register(string $name, string $className, ?string $configClassName = null): void
    {
        self::getInstance()->registerDriver($name, $className, $configClassName);
    }

    /**
     * Unregister a driver from the factory
     *
     * @param  string  $name  The name of the driver to unregister
     * @return bool True if the driver was unregistered, false if it wasn't registered
     */
    public static function unregister(string $name): bool
    {
        return self::getInstance()->unregisterDriver($name);
    }

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
    public static function create(string $driver, ?ConfigInterface $config = null, bool $autoConnect = true): TimeSeriesInterface
    {
        return self::getInstance()->create($driver, $config, $autoConnect);
    }

    /**
     * Get a list of all registered drivers
     *
     * @return string[] Array of driver names
     */
    public static function getAvailableDrivers(): array
    {
        return self::getInstance()->getAvailableDrivers();
    }

    /**
     * Check if a driver is registered
     *
     * @param  string  $name  The name of the driver to check
     * @return bool True if the driver is registered, false otherwise
     */
    public static function hasDriver(string $name): bool
    {
        return self::getInstance()->hasDriver($name);
    }

    /**
     * Get the config class for a driver
     *
     * @param  string  $name  The name of the driver
     * @return class-string|null The fully qualified class name of the config class, or null if the driver is not registered
     */
    public static function getConfigClass(string $name): ?string
    {
        return self::getInstance()->getConfigClass($name);
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
    public static function createConfig(string $driver, array $config = []): ConfigInterface
    {
        return self::getInstance()->createConfig($driver, $config);
    }
}
