<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\QueryCompiler;

final class NullCompiler implements QueryCompiler
{
    public function compile(Query $query): CompiledQuery
    {
        return new class implements CompiledQuery {};
    }
}
