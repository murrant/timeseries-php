<?php

namespace TimeseriesPhp\Core\Graph\Repository;

use TimeseriesPhp\Core\Contracts\GraphRepository;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Exceptions\InvalidGraphDefinitionException;
use TimeseriesPhp\Core\Exceptions\UnknownGraphException;
use TimeseriesPhp\Core\Graph\GraphDefinition;

class RuntimeGraphRepository implements GraphRepository
{
    /** @var array<string, GraphDefinition> */
    private array $graphs = [];

    public function __construct(
        private readonly MetricRepository $metrics,
    ) {}

    public function register(GraphDefinition $graph): void
    {
        $this->graphs[$graph->id] = $graph;
    }

    public function load(string $id): GraphDefinition
    {
        if (! isset($this->graphs[$id])) {
            throw new UnknownGraphException("Unknown graph '{$id}'");
        }

        $graph = $this->graphs[$id];

        foreach ($graph->series as $series) {
            if (! $this->metrics->has($series->metric)) {
                throw new InvalidGraphDefinitionException(
                    "Unknown metric '{$series->metric}'"
                );
            }
        }

        return $graph;
    }

    public function list(): array
    {
        return array_keys($this->graphs);
    }

    public function reload(): void
    {
        // no-op
    }
}
