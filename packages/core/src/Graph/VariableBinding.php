<?php

namespace TimeseriesPhp\Core\Graph;

use TimeseriesPhp\Core\Enum\MatchType;

final readonly class VariableBinding
{
    public function __construct(
        public string $label,
        public mixed $value,
        public MatchType $operator = MatchType::EQUALS,
    ) {}
}
