<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Exceptions\GraphNotFoundException;
use TimeseriesPhp\Core\Exceptions\InvalidGraphDefinitionException;
use TimeseriesPhp\Core\Graph\GraphDefinition;

interface GraphRepository
{
    /**
     * Load a graph definition by ID.
     *
     * @throws GraphNotFoundException
     * @throws InvalidGraphDefinitionException
     */
    public function load(string $id): GraphDefinition;

    /**
     * Return all known graph IDs.
     */
    public function list(): array;

    /**
     * Reload graph definitions from source.
     */
    public function reload(): void;

    /**
     * Register a graph definition.
     */
    public function register(GraphDefinition $graph): void;
}
