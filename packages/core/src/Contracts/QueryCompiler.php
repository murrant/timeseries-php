<?php

namespace TimeseriesPhp\Core\Contracts;

interface QueryCompiler
{
    public function compile(
        Query $query
    ): CompiledQuery;


}
