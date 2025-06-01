<?php

namespace TimeSeriesPhp\Drivers\Aggregate\Factory;

use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Factory\DriverManager;

/**
 * Implementation of DriverFactoryInterface that uses the static DriverManager.
 * This is provided for backward compatibility.
 */
class DriverManagerDriverFactory implements DriverFactoryInterface
{
    /**
     * Create a new TimeSeriesInterface instance.
     *
     * @param  string  $driver  The driver name
     * @param  ConfigInterface|null  $config  The driver configuration
     * @return TimeSeriesInterface The TimeSeriesInterface instance
     */
    public function create(string $driver, ?ConfigInterface $config = null): TimeSeriesInterface
    {
        return DriverManager::create($driver, $config);
    }

    /**
     * Create a configuration instance for a driver.
     *
     * @param  string  $driver  The driver name
     * @param  array<string, mixed>  $config  The configuration options
     * @return ConfigInterface The configuration instance
     */
    public function createConfig(string $driver, array $config = []): ConfigInterface
    {
        return DriverManager::createConfig($driver, $config);
    }
}
