<?php

namespace TimeseriesPhp\Core\Timeseries;

use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;

final readonly class SeriesDefinition
{
    /**
     * @param  array<string, string>  $labels
     * @param  array<string, mixed>  $transformations
     */
    public function __construct(
        public MetricIdentifier $metric,
        public array $labels = [],
        public Aggregation $aggregation = Aggregation::AVG,
        public array $transformations = [], // TODO
    ) {}
}
