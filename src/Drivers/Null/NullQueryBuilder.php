<?php

namespace TimeSeriesPhp\Drivers\Null;

use TimeSeriesPhp\Contracts\Query\QueryBuilderInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Core\Query\RawQuery;

class NullQueryBuilder implements QueryBuilderInterface
{
    public function build(Query $query): RawQueryInterface
    {
        return new RawQuery(json_encode(get_object_vars($query)) ?: '');
    }
}
