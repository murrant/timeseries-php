<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Graph\GraphDefinition;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;

interface GraphCompiler
{
    public function compile(
        GraphDefinition $graph,
        TimeRange $range,
        ?Resolution $resolution = null,
    ): CompiledQuery;
}
