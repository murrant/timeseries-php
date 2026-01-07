<?php

namespace TimeseriesPhp\Driver\RRD\Factories;

use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Driver\RRD\Contracts\LabelStrategy;
use TimeseriesPhp\Driver\RRD\FilenameLabelStrategy;
use TimeseriesPhp\Driver\RRD\RrdConfig;

class LabelStrategyFactory
{
    public function __construct(
        private readonly RrdtoolFactory $factory,
        private readonly MetricRepository $metricRepository,
    ) {}

    public function make(RrdConfig $config): LabelStrategy
    {
        return new FilenameLabelStrategy($config, $this->metricRepository, $this->factory->make($config));
    }
}
