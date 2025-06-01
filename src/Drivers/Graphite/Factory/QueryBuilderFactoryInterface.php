<?php

namespace TimeSeriesPhp\Drivers\Graphite\Factory;

use TimeSeriesPhp\Drivers\Graphite\Query\GraphiteQueryBuilder;

/**
 * Factory interface for creating GraphiteQueryBuilder instances.
 */
interface QueryBuilderFactoryInterface
{
    /**
     * Create a new GraphiteQueryBuilder instance.
     *
     * @param  string  $prefix  The metric prefix
     * @return GraphiteQueryBuilder The GraphiteQueryBuilder instance
     */
    public function create(string $prefix): GraphiteQueryBuilder;
}
