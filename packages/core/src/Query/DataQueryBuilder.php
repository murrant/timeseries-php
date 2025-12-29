<?php

namespace TimeseriesPhp\Core\Query;

use TimeseriesPhp\Core\Exceptions\TimeseriesException;
use TimeseriesPhp\Core\Query\AST\DataQueryNode;
use TimeseriesPhp\Core\Query\AST\MetricNode;
use TimeseriesPhp\Core\Time\TimeRange;

class DataQueryBuilder
{
    private ?TimeRange $range = null;

    /** @var MetricNode[] */
    private array $metrics = [];

    /**
     * Set the global time range for all metrics in this query.
     */
    public function range(TimeRange $range): self
    {
        $this->range = $range;
        return $this;
    }

    /**
     * Add a metric to the query using a scoped MetricBuilder callback.
     * This keeps metric-specific logic (like rate/math) isolated.
     */
    public function addMetric(string $identifier, callable $callback): self
    {
        $builder = new MetricBuilder($identifier);

        // Execute the callback to configure the individual metric
        $callback($builder);

        $this->metrics[] = $builder->build();

        return $this;
    }

    /**
     * Finalize the builder and return the immutable AST Node.
     *
     * @throws TimeseriesException
     */
    public function build(): DataQueryNode
    {
        if ($this->range === null) {
            throw new TimeseriesException("A TimeRange must be provided for a DataQuery.");
        }

        return new DataQueryNode(
            range: $this->range,
            metrics: $this->metrics
        );
    }
}
