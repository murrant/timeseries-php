<?php

namespace TimeseriesPhp\Core\Query\Labels;

use TimeseriesPhp\Core\Contracts\TsdbConnection;
use TimeseriesPhp\Core\Enum\Operator;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Core\Query\AST\LabelQuery;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\LabelQueryResult;

class LabelQueryBuilder
{
    /** @var Filter[] */
    private array $filters = [];

    /** @var string[] */
    private array $metrics = [];

    private ?TimeRange $period = null;

    public function __construct(
        private readonly TsdbConnection $connection
    ) {}

    public function from(string $metric): self
    {
        $this->metrics[] = $metric;

        return $this;
    }

    public function for(TimeRange $period): self
    {
        $this->period = $period;

        return $this;
    }

    public function where(string $label, mixed $value, Operator $op = Operator::Equal): self
    {
        $this->filters[] = new Filter($label, $op, $value);

        return $this;
    }

    public function list(): LabelQueryResult
    {
        return $this->connection->query(new LabelQuery(
            label: null,
            metrics: $this->metrics,
            filters: $this->filters,
            period: $this->period,
        ));
    }

    public function values(string $label): LabelQueryResult
    {
        return $this->connection->query(new LabelQuery(
            label: $label,
            metrics: $this->metrics,
            filters: $this->filters,
            period: $this->period,
        ));
    }
}
