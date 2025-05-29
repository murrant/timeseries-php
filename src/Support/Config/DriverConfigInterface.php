<?php

namespace TimeSeriesPhp\Support\Config;

interface DriverConfigInterface extends ConfigInterface
{
    /**
     * Get the driver name this configuration is for
     */
    public function getDriverName(): string;
}
