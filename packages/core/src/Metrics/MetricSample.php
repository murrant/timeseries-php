<?php

namespace TimeseriesPhp\Core\Metrics;

final readonly class MetricSample
{
    /**
     * @param  array<string, string>  $labels
     */
    public function __construct(
        public MetricIdentifier $metric,
        public array $labels,
        public float|int $value,
        public ?\DateTimeImmutable $timestamp = null,
    ) {}
}
