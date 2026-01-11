<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Metrics\MetricIdentifier;
use TimeseriesPhp\Driver\InfluxDB2\Contracts\FieldStrategy;

/**
 * Strategy for using a single field for each metric
 */

final readonly class FieldStrategySingle implements FieldStrategy
{
    /**
     * Returns the measurement name for a given MetricIdentifier
     */
    public function getMeasurementName(MetricIdentifier $metric): string
    {
        return $metric->key();
    }

    /**
     * Returns the field name for a given MetricIdentifier
     */
    public function getFieldName(MetricIdentifier $metric): string
    {
        return "value";
    }
}
