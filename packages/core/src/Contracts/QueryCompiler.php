<?php

namespace TimeseriesPhp\Core\Contracts;

/** @template TResult of Result */
interface QueryCompiler
{
    /**
     * @param  Query<TResult>  $query
     * @return CompiledQuery<TResult>
     */
    public function compile(Query $query): CompiledQuery;
}
