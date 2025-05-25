<?php

namespace TimeSeriesPhp\Core;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Exceptions\ConfigurationException;
use TimeSeriesPhp\Exceptions\DriverException;

class TSDBFactory
{
    /** @var array<string, class-string> */
    private static array $drivers = [];

    public static function registerDriver(string $name, string $className): void
    {
        self::$drivers[$name] = $className;
    }

    /**
     * @throws DriverException
     * @throws ConfigurationException
     */
    public static function create(string $driver, ConfigInterface $config): TimeSeriesInterface
    {
        if (!isset(self::$drivers[$driver])) {
            throw new DriverException("Driver '{$driver}' not registered");
        }

        $className = self::$drivers[$driver];
        $instance = new $className();

        if (!$instance instanceof TimeSeriesInterface) {
            throw new DriverException("Driver must implement TimeSeriesInterface");
        }

        $instance->connect($config);
        return $instance;
    }

    public static function getAvailableDrivers(): array
    {
        return array_keys(self::$drivers);
    }
}
