<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Factory;

use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolQueryBuilder;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;

/**
 * Default implementation of QueryBuilderFactoryInterface.
 */
class QueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * Create a new RRDtoolQueryBuilder instance.
     *
     * @param  RRDTagStrategyInterface  $tagStrategy  The tag strategy to use
     * @return RRDtoolQueryBuilder The RRDtoolQueryBuilder instance
     */
    public function create(RRDTagStrategyInterface $tagStrategy): RRDtoolQueryBuilder
    {
        return new RRDtoolQueryBuilder($tagStrategy);
    }
}
