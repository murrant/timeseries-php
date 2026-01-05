<?php

namespace TimeseriesPhp\Core;

use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Exceptions\TimeseriesException;
use TimeseriesPhp\Core\Metrics\MetricSample;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;

/**
 * This is a facade for easy access to timeseries operations.
 */
readonly class TSDB
{
    public function __construct(
        private TimeseriesManager $manager
    ) {}

    /**
     * @param string $metric
     * @param string[] $tags
     * @param float|int|null $value
     * @return void
     * @throws TimeseriesException
     */
    public function write(string $metric, array $tags = [], float|int|null $value = null): void
    {
        $metrics = $this->manager->connection()->metrics(); // FIXME wrong metric repository
        $metricId = $metrics->get($metric);

        $this->writer()->write(new MetricSample($metricId, $tags, $value));
    }

    /**
     * @throws TimeseriesException
     */
    public function writer(?string $connection = null): Writer
    {
        return $this->manager->connection($connection)->writer();
    }

    /**
     * @throws TimeseriesException
     */
    public function query(Query $query, ?string $connection = null): TimeseriesQueryResult
    {
        $compiled = $this->manager->connection($connection)->compiler()->compile($query);

        $result = $this->manager->connection($connection)->executor()->execute($compiled);

        if (! $result instanceof TimeseriesQueryResult) {
            throw new TimeseriesException('Invalid query result type');
        }

        return $result;
    }
}
