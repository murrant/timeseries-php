<?php

namespace TimeseriesPhp\Core\Query\AST;

use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;

/**
 * @implements Query<TimeSeriesQueryResult>
 */
readonly class DataQuery implements Query
{
    public function __construct(
        public TimeRange $period,
        public Resolution $resolution,
        /** @var Stream[] */
        public array $streams
    ) {}
}
