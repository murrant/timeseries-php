<?php

namespace TimeseriesPhp\Bridge\Laravel\Commands;

use Illuminate\Console\Command;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Enum\Operator;
use TimeseriesPhp\Core\Exceptions\UnknownMetricException;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Driver\RRD\Contracts\LabelStrategy;

class TsdbRrdLabelsCommand extends Command
{
    protected $signature = 'tsdb:rrd-labels {label-command:names|values|filename} {--metric=*} {--label=*}';

    protected $description = 'Manage RRD labels for metrics';


    public function handle(MetricRepository $repository, LabelStrategy $strategy): void
    {
        $command = $this->argument('label-command');
        $metricKeys = $this->option('metric');

        if (empty($metricKeys)) {
            $this->error('The --metric option is required');
            return;
        }

        try {
            $metrics = $this->resolveMetrics($repository, $metricKeys);

            match ($command) {
                'names' => $this->listLabelNames($strategy, $metrics),
                'values' => $this->listLabelValues($strategy, $metrics),
                'filename' => $this->generateFilename($strategy, $metrics),
                default => $this->error("Unknown command: {$command}. Available commands: names, values, filename"),
            };
        } catch (UnknownMetricException $e) {
            $this->error("Unknown metric: {$e->getMessage()}");
        }
    }

    /**
     * @param string[] $metricKeys
     * @return MetricIdentifier|MetricIdentifier[]
     * @throws UnknownMetricException
     */
    private function resolveMetrics(MetricRepository $repository, array $metricKeys): MetricIdentifier|array
    {
        $metrics = array_map(
            fn(string $key) => $repository->get($key),
            $metricKeys
        );

        return count($metrics) === 1 ? $metrics[0] : $metrics;
    }

    private function listLabelNames(LabelStrategy $strategy, MetricIdentifier|array $metrics): void
    {
        $labelNames = $strategy->listLabelNames($metrics);

        if (empty($labelNames)) {
            $this->info('No labels found for the specified metric(s)');
            return;
        }

        $this->info('Available labels:');
        foreach ($labelNames as $name) {
            $this->line("  - {$name}");
        }
    }

    private function listLabelValues(LabelStrategy $strategy, MetricIdentifier|array $metrics): void
    {
        $labelFilters = $this->option('label');

        if (empty($labelFilters)) {
            $this->error('The --label option is required for the "values" command. Usage: --label=labelName');
            return;
        }

        $labelName = $labelFilters[0];

        // Parse additional filters if provided in key:value format
        $filters = $this->parseFilters(array_slice($labelFilters, 1));

        $values = $strategy->listLabelValues($metrics, $labelName, $filters);

        if (empty($values)) {
            $this->info("No values found for label: {$labelName}");
            return;
        }

        $this->info("Values for label '{$labelName}':");
        foreach ($values as $value) {
            $this->line("  - {$value}");
        }
    }

    private function generateFilename(LabelStrategy $strategy, MetricIdentifier|array $metrics): void
    {
        if (is_array($metrics)) {
            $this->error('The "generate" command requires exactly one metric');
            return;
        }

        $labelFilters = $this->option('label');

        if (empty($labelFilters)) {
            $this->error('The --label option is required for filename generation. Usage: --label=key:value');
            $this->line('');
            $this->line('Available labels for this metric:');
            foreach ($metrics->labels as $label) {
                $this->line("  - {$label}");
            }
            return;
        }

        // Parse label filters into key-value pairs
        $labels = [];
        foreach ($labelFilters as $filter) {
            if (!str_contains($filter, ':')) {
                $this->error("Invalid label format: {$filter}. Expected format: key:value");
                return;
            }

            [$key, $value] = explode(':', $filter, 2);

            if (!in_array($key, $metrics->labels, true)) {
                $this->warn("Label '{$key}' is not defined for metric '{$metrics->namespace}.{$metrics->name}'");
                $this->line('Available labels: ' . implode(', ', $metrics->labels));
            }

            $labels[$key] = $value;
        }

        try {
            $filename = $strategy->generateFilename($metrics, $labels);
            $this->info('Generated filename:');
            $this->line("  {$filename}");
        } catch (\TimeseriesPhp\Driver\RRD\Exceptions\RrdException $e) {
            $this->error("Failed to generate filename: {$e->getMessage()}");
        }
    }

    /**
     * Parse filter strings into Filter objects
     *
     * @param string[] $filterStrings
     * @return Filter[]
     */
    private function parseFilters(array $filterStrings): array
    {
        $filters = [];

        foreach ($filterStrings as $filterString) {
            // Expected formats: key=value, key!=value, key~=pattern, key>value, etc.
            $pattern = '/^([a-zA-Z_][a-zA-Z0-9_]*)([!=<>~]+)(.+)$/';

            if (!preg_match($pattern, $filterString, $matches)) {
                $this->warn("Invalid filter format: {$filterString}. Expected format: key=value or key!=value");
                continue;
            }

            [, $key, $operatorStr, $value] = $matches;

            $operator = match ($operatorStr) {
                '=', '==' => Operator::Equal,
                '!=' => Operator::NotEqual,
                '>' => Operator::GreaterThan,
                '>=' => Operator::GreaterThanOrEqual,
                '<' => Operator::LessThan,
                '<=' => Operator::LessThanOrEqual,
                '~=' => Operator::Regex,
                '!~' => Operator::NotRegex,
                default => null,
            };

            if ($operator === null) {
                $this->warn("Unknown operator: {$operatorStr}. Supported: =, !=, >, >=, <, <=, ~=, !~");
                continue;
            }

            $filters[] = new Filter($key, $operator, $value);
        }

        return $filters;
    }
}
