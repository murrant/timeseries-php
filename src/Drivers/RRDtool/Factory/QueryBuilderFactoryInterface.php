<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Factory;

use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolQueryBuilder;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;

/**
 * Factory interface for creating RRDtoolQueryBuilder instances.
 */
interface QueryBuilderFactoryInterface
{
    /**
     * Create a new RRDtoolQueryBuilder instance.
     *
     * @param  RRDTagStrategyInterface  $tagStrategy  The tag strategy to use
     * @return RRDtoolQueryBuilder The RRDtoolQueryBuilder instance
     */
    public function create(RRDTagStrategyInterface $tagStrategy): RRDtoolQueryBuilder;
}
