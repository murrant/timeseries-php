<?php

namespace TimeseriesPhp\Core\Query\AST\Operations;

use TimeseriesPhp\Core\Contracts\Operation;
use TimeseriesPhp\Core\Enum\OperationType;

readonly class BasicOperation implements Operation
{
    public function __construct(
        public OperationType $type,
        //        public array $args = []
    ) {}

    public function getType(): OperationType
    {
        return $this->type;
    }
}
