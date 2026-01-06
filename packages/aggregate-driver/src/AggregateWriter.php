<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Aggregate;

use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Metrics\MetricSample;

final readonly class AggregateWriter implements Writer
{
    /**
     * @param Writer[] $writers
     */
    public function __construct(
        private array $writers,
    ) {}

    public function write(MetricSample $sample): void
    {
        foreach ($this->writers as $writer) {
            $writer->write($sample);
        }
    }

    /**
     * @param MetricSample[] $samples
     */
    public function writeBatch(array $samples): void
    {
        foreach ($this->writers as $writer) {
            $writer->writeBatch($samples);
        }
    }
}
