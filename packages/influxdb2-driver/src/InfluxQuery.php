<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Enum\QueryType;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;

/**
 * @template TResult of QueryResult
 *
 * @implements CompiledQuery<TResult>
 */
readonly class InfluxQuery implements \Stringable, CompiledQuery
{
    /**
     * @param  string[]  $flux
     */
    public function __construct(
        public array $flux,
        public TimeRange $range,
        public Resolution $resolution,
        public QueryType $type = QueryType::Data,
    ) {}

    public function __toString(): string
    {
        return implode("\n", $this->flux);
    }
}
