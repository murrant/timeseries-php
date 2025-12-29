<?php

namespace App\Actions;

use TimeseriesPhp\Core\Graph\GraphService;
use TimeseriesPhp\Core\Graph\VariableBinding;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

class FetchGraphData
{
    public function __construct(
        private readonly GraphService $graphService,
    ) {}

    public function execute(string $graph_id, ?string $host, ?string $ifName): TimeSeriesResult
    {
        return $this->graphService->render(
            $graph_id,
            TimeRange::lastMinutes(60),
            [
                new VariableBinding('host', $host),
                new VariableBinding('ifName', $ifName),
            ],
        );
    }
}
