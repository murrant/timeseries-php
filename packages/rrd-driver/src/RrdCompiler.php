<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\Query;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\Result;
use TimeseriesPhp\Core\Query\AST\LabelQuery;
use TimeseriesPhp\Core\Results\LabelResult;

/**
 * @template TResult of Result
 *
 * @implements QueryCompiler<TResult>
 */
class RrdCompiler implements QueryCompiler
{
    public function __construct(
        private readonly RrdConfig $config,
    ) {}

    /**
     * @param  Query<TResult>  $query
     * @return CompiledQuery<TResult>
     */
    public function compile(Query $query): CompiledQuery
    {
        if ($query instanceof LabelQuery) {
            /** @var CompiledQuery<LabelResult> $rrdLabelQuery */
            $rrdLabelQuery = new RrdLabelQuery($query);

            return $rrdLabelQuery;
        }

        $options = [];
        $arguments = [];

        // TODO implement compiler to create xport command from query

        return new RrdCommand('xport', $options, $arguments);
    }
}
