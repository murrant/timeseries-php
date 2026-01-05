<?php

namespace App\Actions;

use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Query\Data\QueryBuilder;
use TimeseriesPhp\Core\Query\Data\StreamBuilder;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;
use TimeseriesPhp\Core\TSDB;

class FetchGraphData
{
    public function __construct(
        private readonly TSDB $tsdb,
    ) {}

    /**
     * @return QueryResult<TimeSeriesQueryResult>
     */
    public function execute(string $graph_id, ?string $host = null, ?string $ifName = null, ?TimeRange $range = null): TimeSeriesQueryResult
    {
        return $this->tsdb->query(
            (new QueryBuilder($range))
                ->select('network.port.bytes.in', function (StreamBuilder $b) use ($host, $ifName): void {
                    if ($host) {
                        $b->where('host', $host);
                    }
                    if ($ifName) {
                        $b->where('ifName', $ifName);
                    }

                    $b->rate()
                        ->multiplyBy(8)
                        ->aggregate(Aggregation::Maximum, Aggregation::Minimum, Aggregation::Average)
                        ->as('Inbound');
                })
                ->select('network.port.bytes.out', function (StreamBuilder $b) use ($host, $ifName): void {
                    if ($host) {
                        $b->where('host', $host);
                    }
                    if ($ifName) {
                        $b->where('ifName', $ifName);
                    }

                    $b->rate()
                        ->multiplyBy(8)
                        ->aggregate(Aggregation::Maximum, Aggregation::Minimum, Aggregation::Average)
                        ->as('Outbound');
                })
                ->build()
        );
    }
}
