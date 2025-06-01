<?php

namespace TimeSeriesPhp\Drivers\Graphite\Factory;

use TimeSeriesPhp\Drivers\Graphite\Query\GraphiteQueryBuilder;

/**
 * Default implementation of QueryBuilderFactoryInterface.
 */
class QueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * Create a new GraphiteQueryBuilder instance.
     *
     * @param  string  $prefix  The metric prefix
     * @return GraphiteQueryBuilder The GraphiteQueryBuilder instance
     */
    public function create(string $prefix): GraphiteQueryBuilder
    {
        return new GraphiteQueryBuilder($prefix);
    }
}
