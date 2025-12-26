<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

class InfluxClient implements TsdbClient
{
    public function query(CompiledQuery $query): TimeSeriesResult
    {
        if (! $query instanceof InfluxQuery) {
            throw new \InvalidArgumentException('Query must be an instance of InfluxQuery');
        }

        return new TimeSeriesResult([], $query->range, $query->resolution);
    }
}
