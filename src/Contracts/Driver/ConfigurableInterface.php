<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Contracts\Driver;

/**
 * Interface for drivers that can be configured
 */
interface ConfigurableInterface
{
    /**
     * Configure the driver with the given configuration
     *
     * @param  array<string, mixed>  $config  Configuration for the driver
     */
    public function configure(array $config): void;
}
