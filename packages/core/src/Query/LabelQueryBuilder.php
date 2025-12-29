<?php

namespace TimeseriesPhp\Core\Query;


use TimeseriesPhp\Core\Enum\Operator;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Core\Query\AST\LabelQueryNode;
use TimeseriesPhp\Core\Time\TimeRange;

class LabelQueryBuilder
{
    /** @var Filter[] */
    private array $filters = [];
    private ?TimeRange $range = null;

    /**
     * @param string|null $key If provided, the query fetches values for this specific label.
     * If null, the query fetches a list of all available label keys.
     */
    public function __construct(
        private readonly ?string $key = null
    ) {}

    /**
     * Scope the label discovery to a specific time range.
     */
    public function range(TimeRange $range): self
    {
        $this->range = $range;
        return $this;
    }

    /**
     * Filter the results based on other label criteria.
     * e.g., "Find all 'interface' labels where 'hostname' is 'switch-01'"
     */
    public function where(string $key, mixed $value, Operator $operator = Operator::Equals): self
    {
        $this->filters[] = new Filter($key, $operator, $value);
        return $this;
    }

    /**
     * Finalize the builder into an immutable LabelQueryNode.
     */
    public function build(): LabelQueryNode
    {
        return new LabelQueryNode(
            key: $this->key,
            filters: $this->filters,
            range: $this->range
        );
    }
}
