<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\Driver;
use TimeseriesPhp\Core\Contracts\TsdbCapabilities;

class RrdDriver implements Driver
{
    public function getCapabilities(): TsdbCapabilities
    {
        return new RrdTsdbCapabilities;
    }
}
