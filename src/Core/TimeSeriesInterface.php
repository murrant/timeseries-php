<?php

namespace TimeSeriesPhp\Core;

use TimeSeriesPhp\Config\ConfigInterface;

interface TimeSeriesInterface
{
    public function connect(ConfigInterface $config): bool;
    public function write(DataPoint $dataPoint): bool;
    /** @param DataPoint[] $dataPoints */
    public function writeBatch(array $dataPoints): bool;
    public function query(Query $query): QueryResult;
    public function rawQuery(string $query): QueryResult;
    public function createDatabase(string $database): bool;
    public function listDatabases(): array;
    public function close(): void;
}
