<?php

namespace TimeSeriesPhp\Drivers\Prometheus;

use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\RawQueryContract;
use function time;

class PrometheusDriver extends AbstractTimeSeriesDB
{
    protected function doConnect(): bool
    {
        // Initialize the query builder
        $this->queryBuilder = new PrometheusQueryBuilder();

        $this->connected = true;
        return true;
    }

    public function write(DataPoint $dataPoint): bool
    {
        // Prometheus typically receives metrics via scraping or push gateway
        return true;
    }

    public function rawQuery(RawQueryContract $query): QueryResult
    {
        // Mock Prometheus query result
        $result = [
            ['metric' => ['__name__' => 'cpu_usage'], 'value' => [time(), '0.75']]
        ];

        return new QueryResult($result);
    }

    public function createDatabase(string $database): bool
    {
        // Prometheus doesn't have databases in the traditional sense
        return true;
    }

    public function listDatabases(): array
    {
        return [];
    }

    public function close(): void
    {
        $this->connected = false;
    }
}
