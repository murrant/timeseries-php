<?php

namespace TimeseriesPhp\Driver\InfluxDB2\Contracts;

use TimeseriesPhp\Core\Metrics\MetricIdentifier;

interface FieldStrategy
{
    /**
     * Returns the measurement name for a given MetricIdentifier
     */
    public function getMeasurementName(MetricIdentifier $metric): string;

    /**
     * Returns the field name for a given MetricIdentifier
     */
    public function getFieldName(MetricIdentifier $metric): string;
}
