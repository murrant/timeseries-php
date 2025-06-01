<?php

namespace TimeSeriesPhp\Drivers\Aggregate\Factory;

use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Driver\Factory\DriverFactory as CoreDriverFactory;

/**
 * Default implementation of DriverFactoryInterface that wraps the Core DriverFactory.
 */
class DriverFactory implements DriverFactoryInterface
{
    /**
     * @var CoreDriverFactory The core DriverFactory instance
     */
    private CoreDriverFactory $coreDriverFactory;

    /**
     * Constructor
     *
     * @param  CoreDriverFactory  $coreDriverFactory  The core DriverFactory instance
     */
    public function __construct(CoreDriverFactory $coreDriverFactory)
    {
        $this->coreDriverFactory = $coreDriverFactory;
    }

    /**
     * Create a new TimeSeriesInterface instance.
     *
     * @param  string  $driver  The driver name
     * @param  ConfigInterface|null  $config  The driver configuration
     * @return TimeSeriesInterface The TimeSeriesInterface instance
     */
    public function create(string $driver, ?ConfigInterface $config = null): TimeSeriesInterface
    {
        return $this->coreDriverFactory->create($driver, $config);
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
        return $this->coreDriverFactory->createConfig($driver, $config);
    }
}
