<?php

namespace TimeseriesPhp\Core\Query\AST;

readonly class MetricQueryNode {
    /**
     * @param string|null $search
     * @param Filter[] $filters
     * @param int|null $limit
     */
    public function __construct(
        public ?string $search = null,
        public array $filters = [],
        public ?int $limit = null
    ) {}
}
