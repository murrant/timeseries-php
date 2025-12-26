<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;
use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

class RrdClient implements TsdbClient
{
    public function query(CompiledQuery $query): TimeSeriesResult
    {
        return new TimeSeriesResult([], new TimeRange, Resolution::minutes(5));
    }
}
