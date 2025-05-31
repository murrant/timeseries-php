<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core\Driver;

use Symfony\Component\DependencyInjection\ContainerInterface;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Exceptions\Driver\DriverNotFoundException;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Factory for creating driver instances
 */
class DriverFactory
{
    /**
     * @param  ContainerInterface  $container  The service container
     */
    public function __construct(
        private readonly ContainerInterface $container
    ) {}

    /**
     * Create a driver instance by name
     *
     * @param  string  $name  The name of the driver
     * @param  array<string, mixed>  $config  Configuration for the driver
     * @return TimeSeriesInterface The driver instance
     *
     * @throws DriverNotFoundException If the driver is not found
     * @throws TSDBException If there is an error creating the driver
     */
    public function create(string $name, array $config = []): TimeSeriesInterface
    {
        // Get all tagged driver services
        $drivers = $this->container->getParameter('timeseries.drivers');

        if (! is_array($drivers) || ! isset($drivers[$name])) {
            throw new DriverNotFoundException(sprintf('Driver "%s" not found', $name));
        }

        $serviceId = $drivers[$name];

        if (! is_string($serviceId)) {
            throw new DriverNotFoundException(sprintf('Invalid driver service ID for "%s"', $name));
        }

        if (! $this->container->has($serviceId)) {
            throw new DriverNotFoundException(sprintf('Driver service "%s" not found', $serviceId));
        }

        try {
            // Get the driver instance from the container
            $driver = $this->container->get($serviceId);

            if (! $driver instanceof TimeSeriesInterface) {
                throw new TSDBException(sprintf('Driver "%s" does not implement TimeSeriesInterface', $name));
            }

            // Configure the driver if it implements the ConfigurableInterface
            if ($driver instanceof ConfigurableInterface) {
                $driver->configure($config);
            }

            return $driver;
        } catch (\Exception $e) {
            if ($e instanceof TSDBException) {
                throw $e;
            }

            throw new TSDBException(sprintf('Error creating driver "%s": %s', $name, $e->getMessage()), 0, $e);
        }
    }
}
