<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Attributes\TsdbDriver;
use TimeseriesPhp\Core\Contracts\Driver;
use TimeseriesPhp\Core\Contracts\DriverCapabilities;

#[TsdbDriver(
    name: 'rrd',
    config: RrdConfig::class,
    writer: RrdWriter::class,
    compiler: RrdCompiler::class,
    client: RrdQueryExecutor::class,
    capabilities: RrdCapabilities::class,
)]
class RrdDriver implements Driver
{
    public function getCapabilities(): DriverCapabilities
    {
        return new RrdCapabilities;
    }
}
