<?php

namespace TimeseriesPhp\Core\Timeseries\Labels;

use TimeseriesPhp\Core\Enum\MatchType;

final readonly class LabelFilter
{
    /** @param array<string, LabelMatcher> */
    public function __construct(
        public array $matchers
    ) {}

    public static function match(string $label, mixed $value): self
    {
        return new self([
            $label => new LabelMatcher(MatchType::EQUALS, $value),
        ]);
    }

    public static function fromArray(array $raw): self
    {
        return new self(array_map(LabelMatcher::fromArray(...), $raw));
    }
}
