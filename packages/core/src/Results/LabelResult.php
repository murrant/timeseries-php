<?php

namespace TimeseriesPhp\Core\Results;

use TimeseriesPhp\Core\Contracts\Result;

/**
 * @implements Result<LabelResult>
 */
final readonly class LabelResult implements Result
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
