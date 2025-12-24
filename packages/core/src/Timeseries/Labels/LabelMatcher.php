<?php

namespace TimeseriesPhp\Core\Timeseries\Labels;

use TimeseriesPhp\Core\Enum\MatchType;

final readonly class LabelMatcher
{
    public function __construct(
        public MatchType $type,
        public string $value,
    ) {}

    public static function fromArray(array $raw): self
    {
        return new self(
            type: MatchType::from($raw['type']),
            value: $raw['value'],
        );
    }
}
