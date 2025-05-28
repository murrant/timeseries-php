<?php

namespace TimeSeriesPhp\Core;

class QueryBuilder implements QueryBuilderInterface
{
    public function build(Query $query): RawQueryInterface
    {
        throw new \RuntimeException('The base QueryBuilder cannot build queries. Use a driver-specific QueryBuilder implementation instead.');
    }
}
