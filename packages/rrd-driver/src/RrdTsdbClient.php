<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\Capabilities;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;
use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

class RrdTsdbClient implements TsdbClient
{
    public function getCapabilities(): Capabilities
    {
        return new RrdCapabilities;
    }

    public function query(CompiledQuery $query): TimeSeriesResult
    {
        return new TimeSeriesResult([], new TimeRange, Resolution::minutes(5));
    }
}
