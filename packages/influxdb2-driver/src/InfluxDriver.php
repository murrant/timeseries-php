<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\Capabilities;
use TimeseriesPhp\Core\Contracts\Driver;

class InfluxDriver implements Driver
{

    public function getCapabilities(): Capabilities
    {
        return new InfluxCapabilities();
    }
}
