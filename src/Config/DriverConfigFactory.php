<?php

namespace TimeSeriesPhp\Config;

use TimeSeriesPhp\Exceptions\ConfigurationException;

class DriverConfigFactory
{
    /**
     * Map of driver names to their config classes
     * 
     * @var array<string, class-string<DriverConfigInterface>>
     */
    private static array $driverConfigs = [];

    /**
     * Register a driver configuration class
     */
    public static function registerDriverConfig(string $driver, string $configClass): void
    {
        if (!is_subclass_of($configClass, DriverConfigInterface::class)) {
            throw new ConfigurationException("Config class must implement DriverConfigInterface");
        }

        self::$driverConfigs[$driver] = $configClass;
    }

    /**
     * Create a driver configuration instance
     *
     * @throws ConfigurationException
     */
    public static function create(string $driver, array $config = []): DriverConfigInterface
    {
        if (!isset(self::$driverConfigs[$driver])) {
            throw new ConfigurationException("No configuration class registered for driver: {$driver}");
        }

        $className = self::$driverConfigs[$driver];
        return new $className($config);
    }

    /**
     * Get available driver names
     */
    public static function getAvailableDrivers(): array
    {
        return array_keys(self::$driverConfigs);
    }
}
