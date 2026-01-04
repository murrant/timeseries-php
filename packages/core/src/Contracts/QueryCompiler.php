<?php

namespace TimeseriesPhp\Core\Contracts;

/** @template TResult of QueryResult */
interface QueryCompiler
{
    /**
     * @param  Query<TResult>  $query
     * @return CompiledQuery<TResult>
     */
    public function compile(Query $query): CompiledQuery;
}
