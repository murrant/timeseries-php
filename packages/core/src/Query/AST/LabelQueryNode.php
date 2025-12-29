<?php

namespace TimeseriesPhp\Core\Query\AST;

use TimeseriesPhp\Core\Time\TimeRange;

readonly class LabelQueryNode {
    /**
     * @param Filter[] $filters
     */
    public function __construct(
        public ?string $key = null, // If null, we want all keys. If set, we want values for this key.
        public array $filters = [],
        public ?TimeRange $range = null
    ) {}
}
