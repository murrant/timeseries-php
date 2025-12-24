<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Metrics\MetricSample;

interface TsdbIngestor
{
    public function write(MetricSample $sample): void;
}
