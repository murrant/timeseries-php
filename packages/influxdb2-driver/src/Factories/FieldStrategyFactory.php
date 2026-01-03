<?php

namespace TimeseriesPhp\Driver\InfluxDB2\Factories;

use TimeseriesPhp\Driver\InfluxDB2\Contracts\FieldStrategy;
use TimeseriesPhp\Driver\InfluxDB2\FieldStrategySingle;
use TimeseriesPhp\Driver\InfluxDB2\FieldStrategyMulti;
use TimeseriesPhp\Driver\InfluxDB2\InfluxConfig;

readonly class FieldStrategyFactory
{
    public function make(InfluxConfig $config): FieldStrategy
    {
        if ($config->multiple_fields) {
            return new FieldStrategyMulti();
        }

        return new FieldStrategySingle();
    }
}
