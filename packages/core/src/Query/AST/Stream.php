<?php

namespace TimeseriesPhp\Core\Query\AST;

use TimeseriesPhp\Core\Contracts\Operation;
use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;

readonly class Stream
{
    public function __construct(
        public MetricIdentifier $metric,
        /** @var Filter[] */
        public array $filters,
        /** @var Operation[] */
        public array $pipeline,
        /** @var Aggregation[] */
        public array $aggregations,
        public ?string $alias = null
    ) {}
}
