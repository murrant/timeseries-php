<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\Capabilities;
use TimeseriesPhp\Core\Contracts\Driver;

class RrdDriver implements Driver
{
    public function getCapabilities(): Capabilities
    {
        return new RrdCapabilities;
    }
}
