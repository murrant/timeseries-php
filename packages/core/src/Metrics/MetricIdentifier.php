<?php

namespace TimeseriesPhp\Core\Metrics;

use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\MetricType;

final readonly class MetricIdentifier
{
    /**
     * @param  string[]  $labels  Allowed Labels
     * @param  Aggregation[]  $aggregations  Allowed Aggregations
     * @param  RetentionPolicy[]  $retentionPolicies
     */
    public function __construct(
        public string $namespace,
        public string $name,
        public ?string $unit = null,
        public ?MetricType $type = null,
        public array $labels = [],
        public array $aggregations = [],
        public array $retentionPolicies = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new MetricIdentifier(
            namespace: $raw['namespace'],
            name: $raw['name'],
            unit: $raw['unit'] ?? null,
            type: isset($raw['type']) ? MetricType::from($raw['type']) : null,
            labels: $raw['labels'] ?? [],
            aggregations: array_map(Aggregation::from(...), $raw['aggregations'] ?? []),
            retentionPolicies: array_map(RetentionPolicy::fromArray(...), $raw['retention_policies'] ?? []),
        );
    }

    public function key(): string
    {
        return "{$this->namespace}.{$this->name}";
    }

    public function supportsAggregation(Aggregation $agg): bool
    {
        return in_array($agg, $this->aggregations);
    }
}
