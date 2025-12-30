<?php

namespace TimeseriesPhp\Core\Query\AST\Operations;

use TimeseriesPhp\Core\Contracts\Operation;
use TimeseriesPhp\Core\Enum\MathOperator;
use TimeseriesPhp\Core\Enum\OperationType;

readonly class MathOperation implements Operation
{
    public function __construct(
        public MathOperator $operator,
        public float|int $value
    ) {}

    public function getType(): OperationType
    {
        return OperationType::Math;
    }
}
