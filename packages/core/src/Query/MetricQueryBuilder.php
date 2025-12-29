<?php

namespace TimeseriesPhp\Core\Query;

use TimeseriesPhp\Core\Enum\Operator;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Core\Query\AST\MetricQueryNode;

class MetricQueryBuilder
{
    /** @var Filter[] */
    private array $filters = [];
    private ?string $search = null;
    private ?int $limit = null;

    /**
     * Apply a search pattern (e.g., 'network.port.*' or 'cpu.usage').
     */
    public function search(string $pattern): self
    {
        $this->search = $pattern;
        return $this;
    }

    /**
     * Limit the number of metric names returned.
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Some TSDBs allow filtering metric names by labels.
     */
    public function where(string $key, mixed $value, Operator $operator = Operator::Equals): self
    {
        $this->filters[] = new Filter($key, $operator, $value);
        return $this;
    }

    /**
     * Finalize the builder into an immutable MetricQueryNode.
     */
    public function build(): MetricQueryNode
    {
        return new MetricQueryNode(
            search: $this->search,
            filters: $this->filters,
            limit: $this->limit
        );
    }
}
