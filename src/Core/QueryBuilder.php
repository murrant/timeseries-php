<?php

namespace TimeSeriesPhp\Core;

class QueryBuilder implements QueryBuilderContract
{
    public function build(Query $query): RawQueryContract
    {
        throw new \RuntimeException('The base QueryBuilder cannot build queries. Use a driver-specific QueryBuilder implementation instead.');
    }
}
