<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\GraphCompiler;
use TimeseriesPhp\Core\Graph\GraphDefinition;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;

class RrdCompiler implements GraphCompiler
{

    public function compile(GraphDefinition $graph, TimeRange $range, ?Resolution $resolution = null,): CompiledQuery
    {
        return new RrdQuery();
    }
}
