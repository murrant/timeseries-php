<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Factory;

use TimeSeriesPhp\Drivers\InfluxDB\Query\InfluxDBQueryBuilder;

/**
 * Default implementation of QueryBuilderFactoryInterface.
 */
class QueryBuilderFactory implements QueryBuilderFactoryInterface
{
    /**
     * Create a new InfluxDB query builder.
     *
     * @param  string  $bucket  The bucket name
     * @return InfluxDBQueryBuilder The InfluxDB query builder
     */
    public function create(string $bucket): InfluxDBQueryBuilder
    {
        return new InfluxDBQueryBuilder($bucket);
    }
}
