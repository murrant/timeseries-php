<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Metrics\MetricSample;

interface TsdbConnection
{
    /**
     * @template TResult of QueryResult
     *
     * @param  Query<TResult>  $query
     * @return TResult
     */
    public function query(Query $query): QueryResult;

    public function write(MetricSample $sample): void;
}
