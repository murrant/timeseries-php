<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\TsdbIngestor;
use TimeseriesPhp\Core\Metrics\MetricSample;

final class NullIngestor implements TsdbIngestor
{
    public function write(MetricSample $sample): void
    {
        // Null implementation
    }
}
