<?php

namespace TimeSeriesPhp\Drivers\Aggregate\Factory;

use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;

/**
 * Factory interface for creating TimeSeriesInterface instances.
 */
interface DriverFactoryInterface
{
    /**
     * Create a new TimeSeriesInterface instance.
     *
     * @param  string  $driver  The driver name
     * @param  ConfigInterface|null  $config  The driver configuration
     * @return TimeSeriesInterface The TimeSeriesInterface instance
     */
    public function create(string $driver, ?ConfigInterface $config = null): TimeSeriesInterface;

    /**
     * Create a configuration instance for a driver.
     *
     * @param  string  $driver  The driver name
     * @param  array<string, mixed>  $config  The configuration options
     * @return ConfigInterface The configuration instance
     */
    public function createConfig(string $driver, array $config = []): ConfigInterface;
}
