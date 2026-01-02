<?php

namespace TimeseriesPhp\Driver\RRD\Factories;

use TimeseriesPhp\Driver\RRD\RrdConfig;
use TimeseriesPhp\Driver\RRD\RrdProcess;

class RrdProcessFactory
{
    public function make(RrdConfig $config): RrdProcess
    {
        return new RrdProcess($config);
    }
}
