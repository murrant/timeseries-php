<?php

namespace TimeSeriesPhp\Core;

use TimeSeriesPhp\Config\ConfigInterface;

abstract class AbstractTimeSeriesDB implements TimeSeriesInterface
{
    protected ConfigInterface $config;
    protected bool $connected = false;

    public function connect(ConfigInterface $config): bool
    {
        $this->config = $config;
        return $this->doConnect();
    }

    abstract protected function doConnect(): bool;
    abstract protected function buildQuery(Query $query): string;
    abstract protected function executeQuery(string $query): array;
    abstract protected function formatDataPoint(DataPoint $dataPoint): mixed;

    public function query(Query $query): QueryResult
    {
        $nativeQuery = $this->buildQuery($query);
        $result = $this->executeQuery($nativeQuery);
        return new QueryResult($result);
    }

    public function writeBatch(array $dataPoints): bool
    {
        foreach ($dataPoints as $dataPoint) {
            if (!$this->write($dataPoint)) {
                return false;
            }
        }
        return true;
    }
}
