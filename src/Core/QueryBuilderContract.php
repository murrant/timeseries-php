<?php


namespace TimeSeriesPhp\Core;

interface QueryBuilderContract
{
    public function build(Query $query): RawQueryContract;
}
