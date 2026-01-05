<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Null;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Metrics\MetricSample;

final readonly class NullWriter implements Writer
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger,
    ) {}

    public function write(MetricSample $sample): void
    {
        $this->logger->info("Received write: {$sample->metric->key()} $sample->value {$sample->timestamp->getTimestamp()}", ['labels' => $sample->labels]);
    }

    public function writeBatch(array $samples): void
    {
        foreach ($samples as $sample) {
            $this->write($sample);
        }
    }
}
