<?php

namespace TimeseriesPhp\Core\Graph;

use TimeseriesPhp\Core\Enum\GraphType;

final readonly class GraphStyle
{
    public function __construct(
        public GraphType $type = GraphType::LINE,     // line, area, bar
        public ?string $unit = null,        // override unit
        public bool $stack = false,
        public ?float $min = null,
        public ?float $max = null,
        public array $options = [],       // UI hints only FIXME naughty?
    ) {}

    public static function fromArray(array $style): self
    {
        return new self(
            type: isset($style['type']) ? GraphType::from($style['type']) : GraphType::LINE,
            unit: $style['unit'] ?? null,
            stack: $style['stack'] ?? false,
            min: $style['min'] ?? null,
            max: $style['max'] ?? null,
            options: $style['options'] ?? [],
        );
    }
}
