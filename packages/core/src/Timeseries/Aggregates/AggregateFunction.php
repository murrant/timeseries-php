<?php

namespace TimeseriesPhp\Core\Timeseries\Aggregates;

use TimeseriesPhp\Core\Results\DataPoint;

/**
 * Only implement simple aggregates, avoid math heavy aggregates
 */
interface AggregateFunction
{
    /**
     * @param  DataPoint[]  $points
     */
    public function aggregate(array $points): ?float;
}
