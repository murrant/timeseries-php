<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\TsdbCapabilities;

class InfluxTsdbCapabilities implements TsdbCapabilities
{
    public function supportsRate(): bool
    {
        return false;
    }

    public function supportsHistogram(): bool
    {
        return false;
    }

    public function supportsLabelJoin(): bool
    {
        return false;
    }
}
