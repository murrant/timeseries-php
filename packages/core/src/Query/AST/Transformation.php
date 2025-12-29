<?php

namespace TimeseriesPhp\Core\Query\AST;

use TimeseriesPhp\Core\Enum\TransformationType;

readonly class Transformation {
    /**
     * @param array<mixed> $arguments
     */
    public function __construct(
        public TransformationType $type,
        public array $arguments = []
    ) {}
}
