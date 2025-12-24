<?php

namespace TimeseriesPhp\Core\Timeseries;

use TimeseriesPhp\Core\Metrics\MetricIdentifier;
use TimeseriesPhp\Core\Timeseries\Aggregates\AggregateFunction;
use TimeseriesPhp\Core\Timeseries\Aggregates\Avg;
use TimeseriesPhp\Core\Timeseries\Aggregates\Max;
use TimeseriesPhp\Core\Timeseries\Aggregates\Min;

final readonly class TimeSeries
{
    /**
     * @param  string[]  $labels
     * @param  DataPoint[]  $points
     */
    public function __construct(
        public MetricIdentifier $metric,
        public array $labels,
        public array $points,
    ) {}

    public function min(): ?float
    {
        return $this->aggregate(new Min);
    }

    public function max(): ?float
    {
        return $this->aggregate(new Max);
    }

    public function avg(): ?float
    {
        return $this->aggregate(new Avg);
    }

    public function aggregate(AggregateFunction $aggregation): ?float
    {
        return $aggregation->aggregate($this->points);
    }
}
