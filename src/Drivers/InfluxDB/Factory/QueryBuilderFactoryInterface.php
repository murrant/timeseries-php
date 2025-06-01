<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Factory;

use TimeSeriesPhp\Drivers\InfluxDB\Query\InfluxDBQueryBuilder;

/**
 * Factory interface for creating InfluxDB query builders.
 */
interface QueryBuilderFactoryInterface
{
    /**
     * Create a new InfluxDB query builder.
     *
     * @param  string  $bucket  The bucket name
     * @return InfluxDBQueryBuilder The InfluxDB query builder
     */
    public function create(string $bucket): InfluxDBQueryBuilder;
}
