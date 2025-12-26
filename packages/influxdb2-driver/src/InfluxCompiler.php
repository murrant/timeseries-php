<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\GraphCompiler;
use TimeseriesPhp\Core\Graph\GraphDefinition;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;

class InfluxCompiler implements GraphCompiler
{

    public function compile(GraphDefinition $graph, TimeRange $range, ?Resolution $resolution = null,): InfluxQuery
    {
        return new InfluxQuery();
    }
}
