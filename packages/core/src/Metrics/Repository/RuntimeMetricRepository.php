<?php

namespace TimeseriesPhp\Core\Metrics\Repository;

use LogicException;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Exceptions\UnknownMetricException;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;

final class RuntimeMetricRepository implements MetricRepository
{
    /** @var array<string, MetricIdentifier> */
    private array $metrics = [];

    public function register(MetricIdentifier $metric): void
    {
        $key = $metric->key();

        if (isset($this->metrics[$key])) {
            throw new LogicException(
                "Metric '{$key}' already registered"
            );
        }

        $this->metrics[$key] = $metric;
    }

    public function get(string $key): MetricIdentifier
    {
        if (! isset($this->metrics[$key])) {
            throw new UnknownMetricException($key);
        }

        return $this->metrics[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->metrics[$key]);
    }

    public function all(): iterable
    {
        return $this->metrics;
    }
}
