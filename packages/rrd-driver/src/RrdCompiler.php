<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\GraphCompiler;
use TimeseriesPhp\Core\Graph\BoundGraph;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;

class RrdCompiler implements GraphCompiler
{
    public function compile(BoundGraph $graph, TimeRange $range, ?Resolution $resolution = null): CompiledQuery
    {
        return new RrdQuery;
    }
}
