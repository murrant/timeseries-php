<?php

namespace TimeseriesPhp\Core\Graph\Repository;

use TimeseriesPhp\Core\Contracts\GraphRepository;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Exceptions\GraphNotFoundException;
use TimeseriesPhp\Core\Exceptions\InvalidGraphDefinitionException;
use TimeseriesPhp\Core\Graph\GraphDefinition;

final class YamlGraphRepository implements GraphRepository
{
    private array $graphs = [];

    public function __construct(
        private readonly string $path,
        private readonly MetricRepository $metrics,
    ) {
        $this->reload();
    }

    public function load(string $id): GraphDefinition
    {
        if (! isset($this->graphs[$id])) {
            throw new GraphNotFoundException($id);
        }

        return $this->graphs[$id];
    }

    public function list(): array
    {
        return array_keys($this->graphs);
    }

    public function reload(): void
    {
        $this->graphs = [];

        foreach (glob("{$this->path}/*.yaml") as $file) {
            $definition = $this->loadFile($file);
            $this->graphs[basename($file, '.yaml')] = $definition;
        }
    }

    public function register(GraphDefinition $graph): void
    {
        $this->graphs[$graph->id] = $graph;
    }

    private function loadFile(string $file): GraphDefinition
    {
        $raw = yaml_parse_file($file);

        if (! is_array($raw)) {
            throw new InvalidGraphDefinitionException(
                "Invalid YAML in {$file}"
            );
        }

        return $this->parse($raw, $file);
    }

    private function parse(array $raw, string $source): GraphDefinition
    {
        // Mandatory fields
        foreach (['id', 'title', 'series'] as $key) {
            if (! isset($raw[$key])) {
                throw new InvalidGraphDefinitionException(
                    "Missing '{$key}' in {$source}"
                );
            }
        }

        // Validate metrics early
        foreach ($raw['series'] as $series) {
            if (! $this->metrics->has($series['metric'])) {
                throw new InvalidGraphDefinitionException(
                    "Unknown metric '{$series['metric']}' in {$source}"
                );
            }
        }

        return GraphDefinition::fromArray($raw);
    }
}
