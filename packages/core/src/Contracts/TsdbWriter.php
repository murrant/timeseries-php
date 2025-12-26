<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Metrics\MetricSample;

interface TsdbWriter
{
    public function write(MetricSample $sample): void;
}
