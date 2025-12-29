<?php

namespace TimeseriesPhp\Core\Query\AST;

use TimeseriesPhp\Core\Enum\MathOperator;

readonly class MathOperation {
    public function __construct(
        public MathOperator $operator,
        public float|int $value
    ) {}
}
