<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Graph\GraphDefinition;

interface GraphCompiler
{
    public function compile(GraphDefinition $graph): CompiledQuery;
}
