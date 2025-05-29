<?php

namespace TimeSeriesPhp\Core;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Exceptions\DriverException;

class TSDBFactory
{
    /** @var array<string, class-string> */
    private static array $drivers = [];

    /** @var array<string, class-string> */
    private static array $configClasses = [];

    /**
     * Register a driver with the factory
     *
     * @param  string  $name  The name of the driver
     * @param  class-string  $className  The fully qualified class name of the driver
     * @param  class-string|null  $configClassName  The fully qualified class name of the config class (optional)
     *
     * @throws DriverException If the class doesn't exist or doesn't implement TimeSeriesInterface
     */
    public static function registerDriver(string $name, string $className, ?string $configClassName = null): void
    {
        if (! class_exists($className)) {
            throw new DriverException("Driver class '{$className}' does not exist");
        }

        if (! is_subclass_of($className, TimeSeriesInterface::class)) {
            throw new DriverException("Driver class '{$className}' must implement TimeSeriesInterface");
        }

        // If config class name is not provided, try to infer it from the driver class name
        if ($configClassName === null) {
            $configClassName = self::inferConfigClassName($className);
        }

        if (! class_exists($configClassName)) {
            throw new DriverException("Config class '{$configClassName}' does not exist");
        }

        if (! is_subclass_of($configClassName, ConfigInterface::class)) {
            throw new DriverException("Config class '{$configClassName}' must implement ConfigInterface");
        }

        self::$drivers[$name] = $className;
        self::$configClasses[$name] = $configClassName;
    }

    /**
     * Infer the config class name from the driver class name
     *
     * @param  class-string  $driverClassName  The fully qualified class name of the driver
     * @return class-string The inferred fully qualified class name of the config class
     */
    private static function inferConfigClassName(string $driverClassName): string
    {
        // Get the namespace and class name
        $lastBackslashPos = strrpos($driverClassName, '\\');
        if ($lastBackslashPos === false) {
            // No namespace, just replace "Driver" with "Config" in the class name
            /** @var class-string */
            return str_replace('Driver', 'Config', $driverClassName);
        }

        // Split the class name into namespace and class name
        $namespace = substr($driverClassName, 0, $lastBackslashPos);
        $className = substr($driverClassName, $lastBackslashPos + 1);

        // Replace "Driver" with "Config" only in the class name
        $configClassName = str_replace('Driver', 'Config', $className);

        // Return the fully qualified config class name
        /** @var class-string */
        return $namespace.'\\'.$configClassName;
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
            unset(self::$configClasses[$name]);

            return true;
        }

        return false;
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
        if (! isset(self::$drivers[$driver])) {
            throw new DriverException("Driver '{$driver}' not registered");
        }

        $className = self::$drivers[$driver];
        $instance = new $className;

        if (! $instance instanceof TimeSeriesInterface) {
            throw new DriverException("Driver '{$driver}' must implement TimeSeriesInterface");
        }

        if ($autoConnect) {
            // If no config is provided, create a default one
            if ($config === null) {
                $config = self::createConfig($driver);
            }

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

    /**
     * Get the config class for a driver
     *
     * @param  string  $name  The name of the driver
     * @return class-string|null The fully qualified class name of the config class, or null if the driver is not registered
     */
    public static function getConfigClass(string $name): ?string
    {
        return self::$configClasses[$name] ?? null;
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
        $configClass = self::getConfigClass($driver);

        if ($configClass === null) {
            throw new DriverException("No configuration class registered for driver: {$driver}");
        }

        /** @var ConfigInterface */
        return new $configClass($config);
    }
}
