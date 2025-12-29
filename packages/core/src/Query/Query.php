<?php

namespace TimeseriesPhp\Core\Query;

class Query
{
    /**
     * Start building a query for actual time-series data points.
     */
    public static function data(): DataQueryBuilder
    {
        return new DataQueryBuilder();
    }

    /**
     * Start building a query to discover label keys or values.
     */
    public static function labels(?string $key = null): LabelQueryBuilder
    {
        return new LabelQueryBuilder($key);
    }

    /**
     * Start building a query to list or search for metric names.
     */
    public static function metrics(): MetricQueryBuilder
    {
        return new MetricQueryBuilder();
    }
}
