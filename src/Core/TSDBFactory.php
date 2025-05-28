<?php

namespace TimeSeriesPhp\Core;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Exceptions\DriverException;

class TSDBFactory
{
    /** @var array<string, class-string> */
    private static array $drivers = [];

    /**
     * Register a driver with the factory
     *
     * @param  string  $name  The name of the driver
     * @param  class-string  $className  The fully qualified class name of the driver
     *
     * @throws DriverException If the class doesn't exist or doesn't implement TimeSeriesInterface
     */
    public static function registerDriver(string $name, string $className): void
    {
        if (! class_exists($className)) {
            throw new DriverException("Driver class '{$className}' does not exist");
        }

        if (! is_subclass_of($className, TimeSeriesInterface::class)) {
            throw new DriverException("Driver class '{$className}' must implement TimeSeriesInterface");
        }

        self::$drivers[$name] = $className;
    }

    /**
     * Unregister a driver from the factory
     *
     * @param  string  $name  The name of the driver to unregister
     * @return bool True if the driver was unregistered, false if it wasn't registered
     */
    public static function unregisterDriver(string $name): bool
    {
        if (isset(self::$drivers[$name])) {
            unset(self::$drivers[$name]);

            return true;
        }

        return false;
    }

    /**
     * Create a new instance of a time series database driver
     *
     * @param  string  $driver  The name of the driver to create
     * @param  ConfigInterface  $config  The configuration for the driver
     * @param  bool  $autoConnect  Whether to automatically connect to the database
     * @return TimeSeriesInterface A new instance of the driver
     *
     * @throws DriverException If the driver is not registered or doesn't implement TimeSeriesInterface
     */
    public static function create(string $driver, ConfigInterface $config, bool $autoConnect = true): TimeSeriesInterface
    {
        if (! isset(self::$drivers[$driver])) {
            throw new DriverException("Driver '{$driver}' not registered");
        }

        $className = self::$drivers[$driver];
        $instance = new $className;

        if (! $instance instanceof TimeSeriesInterface) {
            throw new DriverException("Driver '{$driver}' must implement TimeSeriesInterface");
        }

        if ($autoConnect) {
            $instance->connect($config);
        }

        return $instance;
    }

    /**
     * Get a list of all registered drivers
     *
     * @return string[] Array of driver names
     */
    public static function getAvailableDrivers(): array
    {
        return array_keys(self::$drivers);
    }

    /**
     * Check if a driver is registered
     *
     * @param  string  $name  The name of the driver to check
     * @return bool True if the driver is registered, false otherwise
     */
    public static function hasDriver(string $name): bool
    {
        return isset(self::$drivers[$name]);
    }
}
