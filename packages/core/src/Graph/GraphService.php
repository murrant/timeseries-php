<?php

namespace TimeseriesPhp\Core\Graph;

use TimeseriesPhp\Core\Contracts\GraphCompiler;
use TimeseriesPhp\Core\Contracts\GraphRepository;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\TsdbCapabilities;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\MetricType;
use TimeseriesPhp\Core\Exceptions\InvalidGraphException;
use TimeseriesPhp\Core\Exceptions\UnsupportedFeatureException;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;
use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

final readonly class GraphService
{
    public function __construct(
        private GraphRepository $graphs,
        private GraphCompiler $compiler,
        private TsdbClient $client,
        private TsdbCapabilities $capabilities,
        private MetricRepository $metrics,
    ) {}

    public function load(string $name): GraphDefinition
    {
        return $this->graphs->load($name);
    }

    public function render(
        string|GraphDefinition $graph,
        TimeRange $range,
        /** @var VariableBinding[] */
        array $bindings = [],
        ?Resolution $resolution = null,
    ): TimeSeriesResult {

        // 1. Load graph definition
        $definition = is_string($graph)
            ? $this->graphs->load($graph)
            : $graph;

        // 2. Bind template variables
        $graph = $this->bindVariables($definition, $bindings);

        // 3. Validate graph semantics
        $this->validateGraph($graph);

        // 4. Enforce driver capabilities
        $this->enforceCapabilities($graph);

        // 5. Compile graph to backend query
        $compiled = $this->compiler->compile(
            graph: $graph,
            range: $range,
            resolution: $resolution,
        );

        // 6. Execute query
        $result = $this->client->query($compiled);

        // 7. Final sanity checks
        $this->validateResult($result, $definition);

        return $result;
    }

    // -------------------------------------------------------------

    private function validateGraph(BoundGraph $graph): void
    {
        foreach ($graph->definition->series as $series) {
            $metric = $this->metrics->get($series->metric);

            // Metric exists
            if (! $metric) {
                throw new InvalidGraphException(
                    "Unknown metric: {$series->metric}"
                );
            }

            // Aggregation compatibility
            if (! $metric->supportsAggregation($series->aggregation)) {
                throw new InvalidGraphException(
                    "Aggregation '{$series->aggregation->name}' is invalid for metric '{$metric->key()}'"
                );
            }

            // Counter safety
            if ($metric->type === MetricType::COUNTER &&
                $series->aggregation !== Aggregation::RATE) {
                throw new InvalidGraphException(
                    'Counters must be queried using rate()'
                );
            }
        }
    }

    private function enforceCapabilities(BoundGraph $graph): void
    {
        foreach ($graph->definition->requiredCapabilities() as $capability) {
            if (! $this->capabilities->supports($capability)) {
                throw new UnsupportedFeatureException(
                    "Driver does not support '{$capability}'"
                );
            }
        }
    }

    private function validateResult(
        TimeSeriesResult $result,
        GraphDefinition $graph
    ): void {
        // FIXME validate something I guess...
    }

    private function bindVariables(GraphDefinition $graph, array $bindings): BoundGraph
    {
        $variables = [];
        $bindings = array_column($bindings, null, 'label');

        foreach ($graph->variables as $var) {
            $binding = $bindings[$var->name] ?? null;

            if (!$binding && $var->required) {
                throw new InvalidGraphException(
                    "Missing required variable '{$var->name}'"
                );
            }

            if ($binding) {
                if (!in_array($binding->operator, $var->allowedOperators, true)) {
                    throw new InvalidGraphException(
                        "Operator not allowed for '{$var->name}'"
                    );
                }

                if ($binding->value !== null) {
                    $variables[$var->name] = $binding;
                }
            }
        }

        return new BoundGraph($graph, $variables);
    }
}
