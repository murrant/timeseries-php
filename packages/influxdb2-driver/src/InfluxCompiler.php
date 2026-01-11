<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use DateTimeInterface;
use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\Operation;
use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\OperationType;
use TimeseriesPhp\Core\Enum\Operator;
use TimeseriesPhp\Core\Enum\QueryType;
use TimeseriesPhp\Core\Query\AST\DataQuery;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Core\Query\AST\LabelQuery;
use TimeseriesPhp\Core\Query\AST\Operations\MathOperation;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\Stream;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\LabelQueryResult;
use TimeseriesPhp\Core\Results\TimeSeriesQueryResult;
use TimeseriesPhp\Driver\InfluxDB2\Contracts\FieldStrategy;
use TimeseriesPhp\Driver\InfluxDB2\Factories\FieldStrategyFactory;

/** @template TResult of QueryResult */
final readonly class InfluxCompiler implements QueryCompiler
{
    private readonly FieldStrategy $fieldStrategy;

    public function __construct(
        private InfluxConfig $config,
        private MetricRepository $metricRepository,
        private FieldStrategyFactory $fieldStrategyFactory,
    ) {
        $this->fieldStrategy = $fieldStrategyFactory->make($this->config);
    }

    /**
     * @param  Query<TResult>  $query
     * @return CompiledQuery<TResult>
     */
    public function compile(Query $query): CompiledQuery
    {
        if ($query instanceof DataQuery) {
            /** @var CompiledQuery<TResult> */
            return $this->compileDataQuery($query);
        }
        if ($query instanceof LabelQuery) {
            /** @var CompiledQuery<TResult> */
            return $this->compileLabelQuery($query);
        }
        throw new \InvalidArgumentException('Unsupported query type');
    }

    /**
     * @return CompiledQuery<TimeSeriesQueryResult>
     */
    private function compileDataQuery(DataQuery $query): CompiledQuery
    {
        // Define range variables for Flux
        $start = $query->period->start->format(DateTimeInterface::RFC3339);
        $end = $query->period->end->format(DateTimeInterface::RFC3339);

        $flux = [
            sprintf('rangeStart = time(v: "%s")', $start),
            sprintf('rangeStop = time(v: "%s")', $end),
        ];

        foreach ($query->streams as $stream) {
            $flux = [...$flux, ...$this->compileStream($stream, $query->resolution)];
        }

        /** @var InfluxQuery<TimeSeriesQueryResult> $compiled */
        $compiled = new InfluxQuery($flux, $query->period, $query->resolution);

        return $compiled;
    }

    /**
     * @return string[]
     */
    private function compileStream(Stream $stream, Resolution $resolution): array
    {
        $metric = $this->metricRepository->get($stream->metric);

        $flux = [
            sprintf('from(bucket: "%s")', $this->config->bucket),
            '|> range(start: rangeStart, stop: rangeStop)',
            sprintf('|> filter(fn: (r) => r._measurement == "%s")', $this->fieldStrategy->getMeasurementName($metric)),
            sprintf('|> filter(fn: (r) => r._field == "%s")', $this->fieldStrategy->getFieldName($metric)),
        ];

        $flux = $this->compileFluxFilters($stream->filters, $flux);

        $flux = $this->compilePipeline($stream->pipeline, $flux);

        if ($resolution->seconds !== null) {
            foreach ($stream->aggregations as $aggregation) {
                $flux[] = sprintf(
                    '|> aggregateWindow(every: %ds, fn: %s, createEmpty: false)',
                    $resolution->seconds,
                    $this->mapAggregation($aggregation)
                );
            }
        }

        if ($stream->alias) {
            $flux[] = sprintf('|> yield(name: "%s")', $stream->alias);
        }

        return $flux;
    }

    private function mapOperator(Operator $operator): string
    {
        return match ($operator) {
            Operator::Equal => '==',
            Operator::NotEqual => '!=',
            Operator::GreaterThan => '>',
            Operator::GreaterThanOrEqual => '>=',
            Operator::LessThan => '<',
            Operator::LessThanOrEqual => '<=',
            Operator::Regex => '=~',
            Operator::NotRegex => '!~',
            default => throw new \InvalidArgumentException('Unsupported operator'),
        };
    }

    private function mapAggregation(Aggregation $agg): string
    {
        return match ($agg) {
            Aggregation::Average => 'mean',
            Aggregation::Last => 'last',
            Aggregation::Maximum => 'max',
            Aggregation::Median => 'median',
            Aggregation::Minimum => 'min',
            Aggregation::Sum => 'sum',
            default => throw new \InvalidArgumentException('Unsupported aggregation'),
        };
    }

    /**
     * @return CompiledQuery<LabelQueryResult>
     */
    private function compileLabelQuery(LabelQuery $query): CompiledQuery
    {
        if (empty($query->metrics) && empty($query->filters) && $query->period === null) {
            $flux = ['import "influxdata/influxdb/schema"'];
            if ($query->label === null) {
                $flux[] = sprintf('schema.measurements(bucket: "%s")', $this->config->bucket);
            } else {
                $flux[] = sprintf('schema.tagValues(bucket: "%s", tag: "%s")', $this->config->bucket, $query->label);
            }

            /** @var InfluxQuery<LabelQueryResult> $compiled */
            $compiled = new InfluxQuery($flux, new TimeRange(end: new \DateTimeImmutable, duration: new \DateInterval('PT1H')), Resolution::auto(), QueryType::Label);

            return $compiled;
        }

        $period = $query->period ?? new TimeRange(end: new \DateTimeImmutable, duration: new \DateInterval('PT1H'));
        $start = $period->start->format(DateTimeInterface::RFC3339);
        $end = $period->end->format(DateTimeInterface::RFC3339);

        $flux = [
            sprintf('from(bucket: "%s")', $this->config->bucket),
            sprintf('|> range(start: time(v: "%s"), stop: time(v: "%s"))', $start, $end),
        ];

        if (! empty($query->metrics)) {
            $metricFilters = array_map(fn ($m) => sprintf('r._measurement == "%s"', $m), array_unique(array_map(fn ($m) => $this->fieldStrategy->getMeasurementName($this->metricRepository->get($m)), $query->metrics)));

            $flux[] = sprintf('|> filter(fn: (r) => %s)', implode(' or ', $metricFilters));
        }

        $flux = $this->compileFluxFilters($query->filters, $flux);

        if ($query->label === null) {
            $flux[] = '|> keep(columns: ["_measurement"])';
            $flux[] = '|> group()';
            $flux[] = '|> distinct(column: "_measurement")';
        } else {
            $flux[] = sprintf('|> keep(columns: ["%s"])', $query->label);
            $flux[] = '|> group()';
            $flux[] = sprintf('|> distinct(column: "%s")', $query->label);
        }

        /** @var InfluxQuery<LabelQueryResult> $compiled */
        $compiled = new InfluxQuery($flux, $period, Resolution::auto(), QueryType::Label);

        return $compiled;
    }

    /**
     * @param  Filter[]  $filters
     * @param  string[]  $flux
     * @return string[]
     */
    private function compileFluxFilters(array $filters, array $flux): array
    {
        foreach ($filters as $filter) {
            if ($filter->operator === Operator::In) {
                if (! is_array($filter->value)) {
                    throw new \InvalidArgumentException('Operator IN requires an array value');
                }
                $set = implode(', ', array_map(fn ($v) => sprintf('"%s"', $v), $filter->value));
                $flux[] = sprintf('|> filter(fn: (r) => contains(value: r["%s"], set: [%s]))', $filter->key, $set);
            } else {
                $flux[] = sprintf('|> filter(fn: (r) => r["%s"] %s "%s")', $filter->key, $this->mapOperator($filter->operator), $filter->value);
            }
        }

        return $flux;
    }

    /**
     * @param  Operation[]  $pipeline
     * @param  string[]  $flux
     * @return string[]
     */
    private function compilePipeline(array $pipeline, array $flux): array
    {
        foreach ($pipeline as $operation) {
            $flux[] = match ($operation->getType()) {
                OperationType::Rate => '|> derivative(unit: 1s, nonNegative: true)',
                OperationType::Delta => '|> difference(nonNegative: false)',
                OperationType::Math => $this->compileMathOperation($operation),
                default => throw new \InvalidArgumentException(sprintf('Unsupported operation type: %s', $operation->getType()->value)),
            };
        }

        return $flux;
    }

    private function compileMathOperation(Operation $operation): string
    {
        if (! $operation instanceof MathOperation) {
            throw new \InvalidArgumentException('Operation type Math must be an instance of MathOperation');
        }

        $value = sprintf('%.1f', $operation->value);

        return sprintf(
            '|> map(fn: (r) => ({ r with _value: r._value %s %s }))',
            $operation->operator->value,
            $value
        );
    }
}
