<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use TimeSeriesPhp\Drivers\Prometheus\Query\PrometheusQueryBuilder;

/**
 * Factory interface for creating Prometheus query builders.
 */
interface QueryBuilderFactoryInterface
{
    /**
     * Create a new Prometheus query builder.
     *
     * @return PrometheusQueryBuilder The Prometheus query builder
     */
    public function create(): PrometheusQueryBuilder;
}
