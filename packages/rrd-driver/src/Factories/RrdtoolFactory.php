<?php

namespace TimeseriesPhp\Driver\RRD\Factories;

use TimeseriesPhp\Driver\RRD\Contracts\RrdtoolInterface;
use TimeseriesPhp\Driver\RRD\RrdConfig;
use TimeseriesPhp\Driver\RRD\RrdtoolCli;

class RrdtoolFactory
{
    public function make(RrdConfig $config, RrdProcessFactory $processFactory): RrdtoolInterface
    {
        return new RrdtoolCli($config, $processFactory);
    }
}
