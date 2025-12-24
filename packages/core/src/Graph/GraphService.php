<?php

namespace TimeseriesPhp\Core\Graph;

use TimeseriesPhp\Core\Contracts\GraphCompiler;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

class GraphService
{
    public function __construct(
        private readonly GraphCompiler $compiler,
        private readonly TsdbClient $driver,
    ) {}

    public function render(GraphDefinition $graph): TimeSeriesResult
    {
        $query = $this->compiler->compile($graph);

        return $this->driver->query($query);
    }
}
