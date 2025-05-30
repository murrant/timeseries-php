<?php

namespace TimeSeriesPhp\Tests\Core\data;

use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Attributes\Driver;

#[Driver(name: 'test', configClass: TestConfig::class)]
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

    public function query(\TimeSeriesPhp\Core\Query\Query $query): \TimeSeriesPhp\Core\Data\QueryResult
    {
        return new \TimeSeriesPhp\Core\Data\QueryResult([]);
    }

    public function rawQuery(\TimeSeriesPhp\Contracts\Query\RawQueryInterface $query): \TimeSeriesPhp\Core\Data\QueryResult
    {
        return new \TimeSeriesPhp\Core\Data\QueryResult([]);
    }

    public function write(\TimeSeriesPhp\Core\Data\DataPoint $dataPoint): bool
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
