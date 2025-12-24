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
        ?Resolution $resolution = null,
        array $variables = [],
    ): TimeSeriesResult {

        // 1. Load graph definition
        $definition = is_string($graph)
            ? $this->graphs->load($graph)
            : $graph;

        // 2. Bind template variables
        $definition = $definition->withVariables($variables);

        // 3. Validate graph semantics
        $this->validateGraph($definition);

        // 4. Enforce driver capabilities
        $this->enforceCapabilities($definition);

        // 5. Compile graph to backend query
        $compiled = $this->compiler->compile(
            graph: $definition,
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

    private function validateGraph(GraphDefinition $graph): void
    {
        foreach ($graph->series as $series) {
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

    private function enforceCapabilities(GraphDefinition $graph): void
    {
        foreach ($graph->requiredCapabilities() as $capability) {
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
}
