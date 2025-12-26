<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Graph\BoundGraph;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;

interface GraphCompiler
{
    public function compile(
        BoundGraph $graph,
        TimeRange $range,
        ?Resolution $resolution = null,
    ): CompiledQuery;
}
