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
            series: array_map(SeriesDefinition::fromArray(...), $raw['series'] ?? []),
            style: isset($raw['style']) ? GraphStyle::fromArray($raw['style']) : new GraphStyle(GraphType::LINE),
            defaultRange: isset($raw['defaultRange']) && is_array($raw['defaultRange']) ? new TimeRange(...$raw['defaultRange']) : null, // @phpstan-ignore-line
            defaultResolution: isset($raw['defaultResolution']) && is_array($raw['defaultResolution']) ? Resolution::fromArray($raw['defaultResolution']) : null,
            requiredCapabilities: $raw['requiredCapabilities'] ?? [],
            metadata: $raw['metadata'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'variables' => array_map(fn (GraphVariable $var) => $var->toArray(), $this->variables),
            'series' => array_map(fn (SeriesDefinition $series) => $series->toArray(), $this->series),
            'style' => $this->style->toArray(),
            'defaultRange' => $this->defaultRange,
            'defaultResolution' => $this->defaultResolution,
            'requiredCapabilities' => $this->requiredCapabilities,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @return string[]
     */
    public function requiredCapabilities(): array
    {
        return $this->requiredCapabilities; // FIXME empty
    }
}
