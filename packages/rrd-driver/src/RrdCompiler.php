<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\QueryCompiler;

class RrdCompiler implements QueryCompiler
{
    public function compile(Query $query): CompiledQuery
    {
        return new RrdQuery;
    }
}
