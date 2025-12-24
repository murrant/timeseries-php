<?php

namespace TimeseriesPhp\Core\Graph;

use TimeseriesPhp\Core\Enum\GraphType;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;
use TimeseriesPhp\Core\Timeseries\SeriesDefinition;

final readonly class GraphDefinition
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $description,

        /** @var GraphVariable[] */
        public array $variables,

        /** @var SeriesDefinition[] */
        public array $series,

        public GraphStyle $style = new GraphStyle(GraphType::LINE),

        public ?TimeRange $defaultRange = null,
        public ?Resolution $defaultResolution = null,

        /** @var string[] */
        public array $requiredCapabilities = [],

        public array $metadata = [],
    ) {}

    public static function fromArray(array $raw): self
    {
        return new GraphDefinition(
            id: $raw['id'],
            title: $raw['title'],
            description: $raw['description'] ?? null,
            variables: array_map(GraphVariable::fromArray(...), $raw['variables'] ?? []),
            series: $raw['series'] ?? [],
            defaultRange: isset($raw['defaultRange']) && is_array($raw['defaultRange']) ? new TimeRange(...$raw['defaultRange']) : null,
            defaultResolution: new Resolution($raw['defaultResolution'] ?? []),
            style: isset($raw['style']) ? GraphStyle::fromArray($raw['style']) : null,
            requiredCapabilities: $raw['requiredCapabilities'] ?? [],
            metadata: $raw['metadata'] ?? [],
        );
    }

    public function withVariables(array $variables): self
    {
        return new GraphDefinition(
            id: $this->id,
            title: $this->title,
            description: $this->description,
            variables: array_map(fn ($v) => $v->withValue($variables[$v->name] ?? null), $this->variables),
            series: $this->series,
            defaultRange: $this->defaultRange,
            defaultResolution: $this->defaultResolution,
            style: $this->style,
            requiredCapabilities: $this->requiredCapabilities,
            metadata: $this->metadata,
        );
    }

    public function requiredCapabilities(): array
    {
        return $this->requiredCapabilities; // FIXME empty
    }
}
