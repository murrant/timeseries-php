<?php

namespace TimeseriesPhp\Core\Timeseries;

use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Graph\GraphVariable;

final readonly class SeriesDefinition
{
    public function __construct(
        public string $metric,
        public array $variables = [],
        public ?string $legend = null,
        public Aggregation $aggregation = Aggregation::AVG,
        public ?SeriesStyle $style = null,
    ) {}

    public static function fromArray(array $raw): self
    {
        return new SeriesDefinition(
            metric: $raw['metric'],
            variables: array_map(GraphVariable::fromArray(...), $raw['variables'] ?? []),
            legend: $raw['legend'] ?? null,
            aggregation: isset($raw['aggregation']) ? Aggregation::from($raw['aggregation']) : Aggregation::AVG,
            style: $raw['style'] ?? null,
        );
    }
}
