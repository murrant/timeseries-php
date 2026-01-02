<?php

namespace TimeseriesPhp\Driver\RRD\Factories;

use TimeseriesPhp\Driver\RRD\RrdConfig;
use TimeseriesPhp\Driver\RRD\RrdtoolCli;

readonly class RrdtoolFactory
{
    public function __construct(
        private RrdProcessFactory $factory,
    ) {}

    public function make(RrdConfig $config)
    {
        return new RrdtoolCli($config, $this->factory->make($config));
    }
}
