<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Metrics\MetricSample;

final class NullWriter implements Writer
{
    public function write(MetricSample $sample): void
    {
        echo 'Received write: '.$sample->metric->key().' '.$sample->value.' '.$sample->timestamp->getTimestamp().PHP_EOL;
        echo '   Labels: '.json_encode($sample->labels).PHP_EOL;
    }

    public function writeBatch(array $samples): void
    {
        foreach ($samples as $sample) {
            $this->write($sample);
        }
    }
}
