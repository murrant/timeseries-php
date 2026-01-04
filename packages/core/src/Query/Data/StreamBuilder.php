<?php

namespace TimeseriesPhp\Core\Query\Data;

use TimeseriesPhp\Core\Contracts\Operation;
use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\MathOperator;
use TimeseriesPhp\Core\Enum\OperationType;
use TimeseriesPhp\Core\Enum\Operator;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Core\Query\AST\Operations\BasicOperation;
use TimeseriesPhp\Core\Query\AST\Operations\MathOperation;
use TimeseriesPhp\Core\Query\AST\Stream;

class StreamBuilder
{
    /** @var Filter[] */
    private array $filters = [];

    /** @var Operation[] */
    private array $pipeline = [];

    /** @var Aggregation[] */
    private array $aggregations = [];

    private ?string $alias = null;

    public function __construct(private readonly string $metric) {}

    public function where(string $key, mixed $val, Operator $op = Operator::Equal): self
    {
        $this->filters[] = new Filter($key, $op, $val);

        return $this;
    }

    public function rate(): self
    {
        $this->pipeline[] = new BasicOperation(OperationType::Rate);

        return $this;
    }

    public function multiplyBy(float|int $value): self
    {
        $this->pipeline[] = new MathOperation(MathOperator::Multiply, $value);

        return $this;
    }

    public function aggregate(Aggregation ...$functions): self
    {
        $this->aggregations = $functions;

        return $this;
    }

    public function as(string $alias): self
    {
        $this->alias = $alias;

        return $this;
    }

    public function build(): Stream
    {
        return new Stream($this->metric, $this->filters, $this->pipeline, $this->aggregations, $this->alias);
    }
}
