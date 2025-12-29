<?php

namespace TimeseriesPhp\Core\Query\AST;

use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\SortOrder;

readonly class MetricNode {
    /**
     * @param Filter[] $filters
     * @param Transformation[] $transformations
     * @param Aggregation[] $aggregations
     */
    public function __construct(
        public string $identifier,
        public array $filters,
        public array $transformations,
        public array $aggregations,
        public ?string $alias = null,
        public ?int $limit = null,
        public SortOrder $sort = SortOrder::ASC,
        public mixed $fill = null
    ) {}
}
