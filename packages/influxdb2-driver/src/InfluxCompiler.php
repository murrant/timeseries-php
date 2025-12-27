<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use DateTimeInterface;
use TimeseriesPhp\Core\Contracts\GraphCompiler;
use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\MatchType;
use TimeseriesPhp\Core\Graph\BoundGraph;
use TimeseriesPhp\Core\Graph\VariableBinding;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;
use TimeseriesPhp\Core\Timeseries\SeriesDefinition;

final readonly class InfluxCompiler implements GraphCompiler
{
    public function __construct(
        private InfluxConfig $config
    ) {}

    public function compile(BoundGraph $graph, TimeRange $range, ?Resolution $resolution = null): InfluxQuery
    {
        $resolution ??= Resolution::auto();

        // Define range variables for Flux
        $start = $range->start->format(DateTimeInterface::RFC3339);
        $end = $range->end->format(DateTimeInterface::RFC3339);

        $flux = [
            sprintf('rangeStart = time(v: "%s")', $start),
            sprintf('rangeStop = time(v: "%s")', $end),
        ];

        foreach ($graph->definition->series as $series) {
            $flux = [...$flux, ...$this->compileSeries($series, $resolution, $graph->bindings)];
        }

        return new InfluxQuery($flux, $range, $resolution);
    }

    /**
     * @param  VariableBinding[]  $bindings
     * @return string[]
     */
    private function compileSeries(SeriesDefinition $series, Resolution $resolution, array $bindings): array
    {
        $filters = [];
        // Assuming metric name is stored in _measurement
        $filters[] = sprintf('r["_measurement"] == "%s"', $series->metric);

        foreach ($bindings as $binding) {
            $op = match ($binding->operator) {
                MatchType::NOT_EQUALS => '!=',
                MatchType::REGEX => '~=',
                default => '==',
            };
            // Escape double quotes in value if needed
            $val = $binding->value;
            if ($binding->operator !== MatchType::REGEX && is_string($val)) {
                $val = '"'.str_replace('"', '\\"', $val).'"';
            }

            $filters[] = sprintf('r["%s"] %s %s', $binding->label, $op, $val);
        }

        // Also filter by _field if necessary, but usually we just want the value.
        // In InfluxWriter we write 'value=...'. So _field is 'value'.
        $filters[] = 'r["_field"] == "value"';

        $filterString = implode(' and ', $filters);

        $flux = [
            sprintf('from(bucket: "%s")', $this->config->bucket),
            ' |> range(start: rangeStart, stop: rangeStop)',
            ' |> filter(fn: (r) => '.$filterString.')',
        ];

        // Aggregation
        if ($series->aggregation !== Aggregation::NONE) {
            $window = $resolution->seconds ? $resolution->seconds.'s' : '1m'; // Default window if not specified

            if ($series->aggregation === Aggregation::RATE) {
                // Rate usually implies derivative.
                // Calculate rate per second, then downsample
                $flux[] = ' |> derivative(unit: 1s, nonNegative: true)';
                $flux[] = sprintf(' |> aggregateWindow(every: %s, fn: mean, createEmpty: false)', $window);
            } else {
                $aggFunc = match ($series->aggregation) {
                    Aggregation::SUM => 'sum',
                    Aggregation::MIN => 'min',
                    Aggregation::MAX => 'max',
                    default => 'mean',
                };
                $flux[] = sprintf(' |> aggregateWindow(every: %s, fn: %s, createEmpty: false)', $window, $aggFunc);
            }
        }

        // Yield to separate series
        $flux[] = sprintf(' |> yield(name: "%s")', $series->legend ?? $series->metric);

        return $flux;
    }
}
