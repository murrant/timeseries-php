<?php

namespace TimeSeriesPhp\Core;

interface QueryBuilderInterface
{
    public function build(Query $query): RawQueryInterface;
}
