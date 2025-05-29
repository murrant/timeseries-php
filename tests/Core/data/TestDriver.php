<?php

namespace TimeSeriesPhp\Tests\Core\data;

use TimeSeriesPhp\Core\TimeSeriesInterface;
use TimeSeriesPhp\Support\Config\ConfigInterface;

class TestDriver implements TimeSeriesInterface
{
    public function connect(ConfigInterface $config): bool
    {
        return true;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function query(\TimeSeriesPhp\Core\Query $query): \TimeSeriesPhp\Core\QueryResult
    {
        return new \TimeSeriesPhp\Core\QueryResult([]);
    }

    public function rawQuery(\TimeSeriesPhp\Support\Query\RawQueryInterface $query): \TimeSeriesPhp\Core\QueryResult
    {
        return new \TimeSeriesPhp\Core\QueryResult([]);
    }

    public function write(\TimeSeriesPhp\Core\DataPoint $dataPoint): bool
    {
        return true;
    }

    public function writeBatch(array $dataPoints): bool
    {
        return true;
    }

    public function createDatabase(string $database): bool
    {
        return true;
    }

    public function deleteDatabase(string $database): bool
    {
        return true;
    }

    public function getDatabases(): array
    {
        return [];
    }

    public function deleteMeasurement(string $measurement, ?\DateTime $start = null, ?\DateTime $stop = null): bool
    {
        return true;
    }

    public function close(): void {}
}
