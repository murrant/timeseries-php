<?php

namespace TimeseriesPhp\Driver\RRD\Factories;

use TimeseriesPhp\Driver\RRD\Contracts\LabelStrategy;
use TimeseriesPhp\Driver\RRD\FilenameLabelStrategy;
use TimeseriesPhp\Driver\RRD\RrdConfig;

class LabelStrategyFactory
{
    public function __construct(
        private readonly RrdtoolFactory $factory,
    ) {}

    public function make(RrdConfig $config): LabelStrategy
    {
        return new FilenameLabelStrategy($config, $this->factory->make($config));
    }
}
