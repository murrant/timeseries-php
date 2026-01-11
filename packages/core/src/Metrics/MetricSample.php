<?php

namespace TimeseriesPhp\Core\Metrics;

use TimeseriesPhp\Core\Contracts\MetricRepository;

final readonly class MetricSample
{
    public MetricIdentifier $metric;

    /**
     * @param  array<string, string>  $labels
     */
    public function __construct(
        private readonly string $metricId,
        public array $labels,
        public float|int $value,
        public ?\DateTimeImmutable $timestamp = null,
    ) {
        $metrics = app()->make(MetricRepository::class);

        $this->metric = $metrics->get($metricId);
    }
}
