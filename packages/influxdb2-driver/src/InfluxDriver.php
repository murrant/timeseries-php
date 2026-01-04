<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Attributes\TsdbDriver;
use TimeseriesPhp\Core\Contracts\Driver;
use TimeseriesPhp\Core\Contracts\DriverCapabilities;

#[TsdbDriver(
    name: 'influxdb2',
    config: InfluxConfig::class,
    writer: InfluxWriter::class,
    compiler: InfluxCompiler::class,
    client: InfluxQueryExecutor::class,
    capabilities: InfluxCapabilities::class,
)]
class InfluxDriver implements Driver
{
    public function getCapabilities(): DriverCapabilities
    {
        return new InfluxCapabilities;
    }
}
