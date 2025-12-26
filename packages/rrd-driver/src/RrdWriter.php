<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\TsdbWriter;
use TimeseriesPhp\Core\Metrics\MetricSample;

class RrdWriter implements TsdbWriter
{
    public function write(MetricSample $sample): void
    {
        // TODO: Implement write() method.
    }
}
