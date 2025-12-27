<?php

namespace TimeseriesPhp\Core\Graph;

final readonly class BoundGraph
{
    public function __construct(
        public GraphDefinition $definition,
        /** @var array<string, VariableBinding> */
        public array $bindings,
    ) {}
}
