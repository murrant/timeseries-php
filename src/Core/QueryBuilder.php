<?php

namespace TimeSeriesPhp\Core;

class QueryBuilder implements QueryBuilderContract
{

    public function build(Query $query): RawQueryContract
    {
        return new RawQuery($query->__toString());
    }
}
