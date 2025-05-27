<?php

namespace TimeSeriesPhp\Core;

use TimeSeriesPhp\Config\ConfigInterface;

abstract class AbstractTimeSeriesDB implements TimeSeriesInterface
{
    protected ConfigInterface $config;

    protected bool $connected = false;

    protected QueryBuilderContract $queryBuilder;

    public function connect(ConfigInterface $config): bool
    {
        $this->config = $config;

        return $this->doConnect();
    }

    abstract protected function doConnect(): bool;

    public function query(Query $query): QueryResult
    {
        return $this->rawQuery($this->queryBuilder->build($query));
    }

    public function writeBatch(array $dataPoints): bool
    {
        foreach ($dataPoints as $dataPoint) {
            if (! $this->write($dataPoint)) {
                return false;
            }
        }

        return true;
    }
}
