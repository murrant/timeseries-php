<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Attributes\TsdbDriver;
use TimeseriesPhp\Core\Contracts\Driver;
use TimeseriesPhp\Core\Contracts\TsdbCapabilities;

#[TsdbDriver(
    name: 'influxdb2',
    config: InfluxConfig::class,
    writer: InfluxWriter::class,
    compiler: InfluxCompiler::class,
    client: InfluxClient::class,
    capabilities: InfluxCapabilities::class,
)]
class InfluxDriver implements Driver
{
    public function getCapabilities(): TsdbCapabilities
    {
        return new InfluxCapabilities;
    }
}
