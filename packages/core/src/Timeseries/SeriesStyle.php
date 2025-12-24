<?php

namespace TimeseriesPhp\Core\Timeseries;

use TimeseriesPhp\Core\Enum\SeriesType;

final readonly class SeriesStyle
{
    public function __construct(
        public SeriesType $type = SeriesType::LINE,     // line, area
        public ?string $axis = null,         // left/right
        public bool $hidden = false,
        public ?string $color = null,
        public array $options = [],        // UI hints FIXME naughty?
    ) {}

    public static function fromArray(array $raw): self
    {
        return new SeriesStyle(
            type: isset($raw['type']) ? SeriesType::from($raw['type']) : SeriesType::LINE,
            axis: $raw['axis'] ?? null,
            hidden: $raw['hidden'] ?? false,
            color: $raw['color'] ?? null,
            options: $raw['options'] ?? [],
        );
    }
}
