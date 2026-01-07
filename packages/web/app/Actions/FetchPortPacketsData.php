<?php

namespace App\Actions;

use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Query\Data\QueryBuilder;
use TimeseriesPhp\Core\Query\Data\StreamBuilder;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;
use TimeseriesPhp\Core\TSDB;

class FetchPortPacketsData
{
    public function __construct(
        private readonly TSDB $tsdb,
    ) {}

    /**
     * @return QueryResult<TimeSeriesQueryResult>
     */
    public function execute(?string $host = null, ?string $ifName = null, ?TimeRange $range = null): TimeSeriesQueryResult
    {
        $builder = new QueryBuilder($range);

        $builder->select('network.port.packets.in', function (StreamBuilder $b) use ($host, $ifName): void {
            if ($host) {
                $b->where('host', $host);
            }
            if ($ifName) {
                $b->where('ifName', $ifName);
            }

            $b->rate()
                ->aggregate(Aggregation::Maximum, Aggregation::Minimum, Aggregation::Average)
                ->as('Inbound');
        })
        ->select('network.port.packets.out', function (StreamBuilder $b) use ($host, $ifName): void {
            if ($host) {
                $b->where('host', $host);
            }
            if ($ifName) {
                $b->where('ifName', $ifName);
            }

            $b->rate()
                ->aggregate(Aggregation::Maximum, Aggregation::Minimum, Aggregation::Average)
                ->as('Outbound');
        });

        return $this->tsdb->query($builder->build());
    }
}
