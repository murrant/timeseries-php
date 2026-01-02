<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Query\AST\LabelQuery;
use TimeseriesPhp\Core\Results\LabelResult;

/**
 * @implements CompiledQuery<LabelResult>
 */
final readonly class RrdLabelQuery implements CompiledQuery
{
    public function __construct(
        public LabelQuery $query) {}
}
