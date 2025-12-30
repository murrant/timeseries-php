<?php

namespace TimeseriesPhp\Core\Contracts;

/**
 * @template TResult of Result
 */
interface TsdbClient
{
    /**
     * @param  CompiledQuery<TResult>  $query
     * @return TResult
     */
    public function execute(CompiledQuery $query): Result;
}
