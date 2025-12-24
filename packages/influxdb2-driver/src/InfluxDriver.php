<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\Driver;
use TimeseriesPhp\Core\Contracts\TsdbCapabilities;

class InfluxDriver implements Driver
{
    public function getCapabilities(): TsdbCapabilities
    {
        return new InfluxTsdbCapabilities;
    }
}
