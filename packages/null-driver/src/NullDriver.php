<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Attributes\TsdbDriver;
use TimeseriesPhp\Core\Contracts\Driver;
use TimeseriesPhp\Core\Contracts\TsdbCapabilities;

#[TsdbDriver(
    name: 'null',
    config: NullConfig::class,
    writer: NullWriter::class,
    compiler: NullCompiler::class,
    client: NullClient::class,
    capabilities: NullCapabilities::class,
)]
class NullDriver implements Driver
{
    public function getCapabilities(): TsdbCapabilities
    {
        return new NullCapabilities;
    }
}
