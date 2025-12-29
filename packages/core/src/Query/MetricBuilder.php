<?php

namespace TimeseriesPhp\Core\Query;

use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\MathOperator;
use TimeseriesPhp\Core\Enum\Operator;
use TimeseriesPhp\Core\Enum\SortOrder;
use TimeseriesPhp\Core\Enum\TransformationType;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Core\Query\AST\MathOperation;
use TimeseriesPhp\Core\Query\AST\MetricNode;
use TimeseriesPhp\Core\Query\AST\Transformation;

class MetricBuilder
{
    /** @var Filter[] */
    private array $filters = [];
    /** @var Transformation[] */
    private array $transformations = [];
    /** @var Aggregation[] */
    private array $aggregations = [];
    private ?string $alias = null;
    private ?int $limit = null;
    private SortOrder $sort = SortOrder::ASC;
    private mixed $fill = null;

    /**
     * @param string $identifier The metric name (e.g., 'network.port.bytes.in')
     */
    public function __construct(
        private readonly string $identifier
    ) {}

    /**
     * Add a filter to this specific metric (e.g., tag or label filtering).
     */
    public function where(string $key, mixed $value, Operator $operator = Operator::Equals): self
    {
        $this->filters[] = new Filter($key, $operator, $value);
        return $this;
    }

    /**
     * Converts counter values into a rate-per-second.
     */
    public function rate(): self
    {
        $this->transformations[] = new Transformation(TransformationType::Rate);
        return $this;
    }

    /**
     * Calculates the absolute difference between consecutive samples.
     */
    public function delta(): self
    {
        $this->transformations[] = new Transformation(TransformationType::Delta);
        return $this;
    }

    /**
     * Apply a mathematical expression to the metric values (e.g., '* 8').
     */
    public function math(MathOperator $operator, float|int $value): self {
        $this->transformations[] = new Transformation(
            TransformationType::Math,
            [new MathOperation($operator, $value)]
        );
        return $this;
    }

    // Convenience helpers for common tasks
    public function multiplyBy(float|int $value): self {
        return $this->math(MathOperator::Multiply, $value);
    }

    public function divideBy(float|int $value): self {
        return $this->math(MathOperator::Divide, $value);
    }

    /**
     * Define the aggregations needed.
     * @param Aggregation[] $aggregations List of enums like [Aggregation::Average, Aggregation::Max]
     */
    public function aggregate(array $aggregations): self
    {
        $this->aggregations = $aggregations;

        return $this;
    }

    /**
     * Define the downsampling window.
     * @param string $window Interval like '5m' or '1h'
     */
    public function groupBy(string $window): self
    {
        $this->transformations[] = new Transformation(TransformationType::GroupBy, [$window]);

        return $this;
    }

    /**
     * Shortcut to fetch only the most recent data point.
     */
    public function latest(): self
    {
        $this->sort = SortOrder::DESC;
        $this->limit = 1;
        return $this;
    }

    /**
     * Define how to handle gaps in data (e.g., fill with 0, null, or 'none').
     */
    public function fill(mixed $value): self
    {
        $this->fill = $value;
        return $this;
    }

    /**
     * Assign a human-readable name to the result series.
     */
    public function alias(string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * Manual control over sorting if 'latest()' is not used.
     */
    public function sortBy(SortOrder $order): self
    {
        $this->sort = $order;
        return $this;
    }

    /**
     * Compiles the builder state into an immutable AST Node.
     */
    public function build(): MetricNode
    {
        return new MetricNode(
            identifier: $this->identifier,
            filters: $this->filters,
            transformations: $this->transformations,
            aggregations: $this->aggregations,
            alias: $this->alias,
            limit: $this->limit,
            sort: $this->sort,
            fill: $this->fill
        );
    }
}
