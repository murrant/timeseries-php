<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Exceptions\UnknownMetricException;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;

interface MetricRepository
{
    /**
     * @throws UnknownMetricException
     */
    public function get(string $key): MetricIdentifier;

    public function has(string $key): bool;

    /**
     * @return iterable<MetricIdentifier>
     */
    public function all(): iterable;

    /**
     * Register a metric.
     */
    public function register(MetricIdentifier $metric): void;
}
