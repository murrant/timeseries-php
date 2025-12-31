<?php

namespace TimeseriesPhp\Driver\RRD;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\TsdbWriter;
use TimeseriesPhp\Core\Metrics\MetricSample;

class RrdWriter implements TsdbWriter
{
    public function __construct(private readonly ?LoggerInterface $logger = new NullLogger) {}

    public function write(MetricSample $sample): void
    {
        $this->logger->debug('Writing to RRD', ['sample' => $sample]);
        // TODO: Implement write() method.
    }

    public function writeBatch(array $samples): void
    {
        foreach ($samples as $sample) {
            $this->write($sample);
        }
    }
}
