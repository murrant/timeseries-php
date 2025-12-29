<?php

namespace TimeseriesPhp\Core\Query\AST;

use TimeseriesPhp\Core\Enum\Operator;

readonly class Filter
{
    public function __construct(
        public string $key,
        public Operator $operator,
        public mixed $value
    ) {}
}
