<?php

namespace TimeseriesPhp\Core\Timeseries\Aggregates;

use TimeseriesPhp\Core\Results\DataPoint;

class Min implements AggregateFunction
{
    /**
     * @param  DataPoint[]  $points
     */
    public function aggregate(array $points): ?float
    {
        if (empty($points)) {
            return null;
        }

        return array_reduce($points, fn ($carry, $item) => min($carry ?? $item->value, $item->value));
    }
}
