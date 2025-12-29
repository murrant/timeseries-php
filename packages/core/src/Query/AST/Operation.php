<?php

namespace TimeseriesPhp\Core\Query\AST;

readonly class Operation {
    /**
     * @param string $function e.g., 'avg', 'sum', 'rate'
     * @param array<mixed> $parameters
     */
    public function __construct(
        public string $function,
        public array $parameters = []
    ) {}
}
