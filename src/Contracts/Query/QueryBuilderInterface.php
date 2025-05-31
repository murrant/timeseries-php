<?php

namespace TimeSeriesPhp\Contracts\Query;

use TimeSeriesPhp\Core\Query\Query;

interface QueryBuilderInterface
{
    public function build(Query $query): RawQueryInterface;
}
