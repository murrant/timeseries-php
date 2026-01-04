<?php

namespace TimeseriesPhp\Core\Results;

use TimeseriesPhp\Core\Contracts\QueryResult;

/**
 * @implements QueryResult<LabelQueryResult>
 */
final readonly class LabelQueryResult implements QueryResult
{
    /**
     * @param  string[]  $labels
     * @param  string[]  $values
     */
    public function __construct(
        public array $labels,
        public array $values,
    ) {}
}
