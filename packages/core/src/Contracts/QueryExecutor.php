<?php

namespace TimeseriesPhp\Core\Contracts;

/**
 * @template TResult of QueryResult
 */
interface QueryExecutor
{
    /**
     * @param  CompiledQuery<TResult>  $query
     * @return TResult
     */
    public function execute(CompiledQuery $query): QueryResult;
}
