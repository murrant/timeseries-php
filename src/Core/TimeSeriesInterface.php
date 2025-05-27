<?php

namespace TimeSeriesPhp\Core;

use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;

interface TimeSeriesInterface
{
    public function connect(ConfigInterface $config): bool;

    /** @throws WriteException */
    public function write(DataPoint $dataPoint): bool;
    /**
     * @param DataPoint[] $dataPoints
     * @throws WriteException
     */
    public function writeBatch(array $dataPoints): bool;
    /** @throws QueryException */
    public function query(Query $query): QueryResult;
    /** @throws QueryException */
    public function rawQuery(RawQueryContract $query): QueryResult;
    public function createDatabase(string $database): bool;
    public function listDatabases(): array;
    public function close(): void;
}
