<?php

namespace TimeseriesPhp\Core\Query\AST;

use TimeseriesPhp\Core\Time\TimeRange;

readonly class DataQueryNode
{
    /** @param MetricNode[] $metrics */
    public function __construct(
        public TimeRange $range,
        public array $metrics
    ) {}
}
