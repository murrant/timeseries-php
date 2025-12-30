<?php

namespace TimeseriesPhp\Core\Results;

use TimeseriesPhp\Core\Timeseries\Aggregates\AggregateFunction;
use TimeseriesPhp\Core\Timeseries\Aggregates\Avg;
use TimeseriesPhp\Core\Timeseries\Aggregates\Max;
use TimeseriesPhp\Core\Timeseries\Aggregates\Min;

final readonly class TimeSeries implements \JsonSerializable
{
    /**
     * @param  string[]  $labels
     * @param  DataPoint[]  $points
     */
    public function __construct(
        public string $metric,
        public ?string $alias,
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

    public function jsonSerialize(): array
    {
        return [
            'metric' => $this->metric,
            'labels' => $this->labels,
            'points' => array_map(fn (DataPoint $point) => ['timestamp' => $point->timestamp, 'value' => $point->value], $this->points),
        ];
    }
}
