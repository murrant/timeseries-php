<?php

namespace TimeSeriesPhp\Support\Query;

use TimeSeriesPhp\Core\Query;

interface QueryBuilderInterface
{
    public function build(Query $query): RawQueryInterface;
}
