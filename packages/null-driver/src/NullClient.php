<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;
use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

final class NullClient implements TsdbClient
{
    public function query(CompiledQuery $query): TimeSeriesResult
    {
        return new TimeSeriesResult([], TimeRange::lastMinutes(60), Resolution::auto());
    }
}
