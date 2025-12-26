<?php

namespace TimeseriesPhp\Core\Graph;

final class BoundGraph
{
    public function __construct(
        public readonly GraphDefinition $definition,
        /** @var array<string, VariableBinding> */
        public readonly array $bindings,
    ) {}
}
