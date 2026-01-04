<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;

final class NullClient implements QueryExecutor
{
    public function execute(CompiledQuery $query): QueryResult
    {
        return new TimeSeriesQueryResult([], TimeRange::lastMinutes(60), Resolution::auto());
    }
}
