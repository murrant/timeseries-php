<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\TsdbWriter;
use TimeseriesPhp\Core\Metrics\MetricSample;

final class NullWriter implements TsdbWriter
{
    public function write(MetricSample $sample): void
    {
        echo 'Received write: '.$sample->metric->key().' '.$sample->value.' '.$sample->timestamp->getTimestamp().PHP_EOL;
        echo '   Labels: '.json_encode($sample->labels).PHP_EOL;
    }
}
