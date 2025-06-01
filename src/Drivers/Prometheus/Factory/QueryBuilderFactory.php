<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use TimeSeriesPhp\Drivers\Prometheus\Query\PrometheusQueryBuilder;

/**
 * Default implementation of QueryBuilderFactoryInterface.
 */
class QueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * Create a new Prometheus query builder.
     *
     * @return PrometheusQueryBuilder The Prometheus query builder
     */
    public function create(): PrometheusQueryBuilder
    {
        return new PrometheusQueryBuilder;
    }
}
