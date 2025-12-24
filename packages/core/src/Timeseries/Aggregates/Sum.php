<?php

namespace TimeseriesPhp\Core\Timeseries\Aggregates;

class Sum implements AggregateFunction
{
    public function aggregate(array $points): ?float
    {
        return array_sum(array_map(fn ($point) => $point->value, $points));
    }
}
