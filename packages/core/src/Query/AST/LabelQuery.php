<?php

namespace TimeseriesPhp\Core\Query\AST;

use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Results\LabelQueryResult;

/**
 * @implements Query<LabelQueryResult>
 */
final readonly class LabelQuery implements Query
{
    public function __construct(
        public ?string $label,
        /** @var string[] */
        public array $metrics,
        /** @var Filter[] */
        public array $filters,
        public ?TimeRange $period,
    ) {}
}
