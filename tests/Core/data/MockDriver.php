<?php

namespace TimeSeriesPhp\Tests\Core\data;

use TimeSeriesPhp\Core\TimeSeriesInterface;
use TimeSeriesPhp\Support\Config\ConfigInterface;

class MockDriver implements TimeSeriesInterface
{
    public static bool $connectCalled = false;

    public static ?ConfigInterface $lastConfig = null;

    public function connect(ConfigInterface $config): bool
    {
        self::$connectCalled = true;
        self::$lastConfig = $config;

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
