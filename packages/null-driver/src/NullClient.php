<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\TimeSeriesResult;

final class NullClient implements TsdbClient
{
    public function query(CompiledQuery $query): TimeSeriesResult
    {
        return new TimeSeriesResult([], TimeRange::lastMinutes(60), Resolution::auto());
    }
}
