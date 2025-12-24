<?php

namespace TimeseriesPhp\Core\Timeseries\Aggregates;

class Avg implements AggregateFunction
{
    public function aggregate(array $points): ?float
    {
        if (empty($points)) {
            return null;
        }

        return array_reduce($points, fn ($carry, $item) => $carry + $item->value, 0.0) / count($points);
    }
}
