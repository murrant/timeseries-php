<?php

namespace TimeseriesPhp\Driver\RRD;

use DateTimeImmutable;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\TimeSeriesResult;

class RrdClient implements TsdbClient
{
    public function query(CompiledQuery $query): TimeSeriesResult
    {
        return new TimeSeriesResult([], new TimeRange(new DateTimeImmutable, new DateTimeImmutable), Resolution::minutes(5));
    }
}
