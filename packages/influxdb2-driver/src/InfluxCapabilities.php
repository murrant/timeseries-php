<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\DriverCapabilities;

class InfluxCapabilities implements DriverCapabilities
{
    public function supportsRate(): bool
    {
        return true;
    }

    public function supportsHistogram(): bool
    {
        return true;
    }

    public function supportsLabelJoin(): bool
    {
        return true;
    }

    public function supports(string $capability): bool
    {
        return true;
    }
}
