<?php

namespace TimeseriesPhp\Core\Timeseries\Aggregates;

class Max implements AggregateFunction
{
    public function aggregate(array $points): ?float
    {
        return array_reduce($points, fn ($carry, $point) => max($carry ?? $point->value, $point->value));
    }
}
