<?php

namespace TimeSeriesPhp\Support\Config;

use TimeSeriesPhp\Contracts\Config\DriverConfigInterface;

abstract class AbstractDriverConfig extends AbstractConfig implements DriverConfigInterface
{
    /**
     * The name of the driver this configuration is for
     */
    protected string $driverName;

    /**
     * Get the driver name this configuration is for
     */
    public function getDriverName(): string
    {
        return $this->driverName;
    }
}
