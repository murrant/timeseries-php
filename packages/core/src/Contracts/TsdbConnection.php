<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Metrics\MetricSample;

interface TsdbConnection
{
    /**
     * @template TResult of Result
     *
     * @param  Query<TResult>  $query
     * @return TResult
     */
    public function query(Query $query): Result;

    public function write(MetricSample $sample): void;
}
