<?php

namespace TimeseriesPhp\Driver\RRD;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Exceptions\UnknownMetricException;
use TimeseriesPhp\Core\Metrics\MetricSample;
use TimeseriesPhp\Driver\RRD\Contracts\LabelStrategy;
use TimeseriesPhp\Driver\RRD\Contracts\RrdtoolInterface;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdNotFoundException;

class RrdWriter implements Writer
{
    public function __construct(
        private readonly RrdConfig $config,
        private readonly MetricRepository $metrics,
        private readonly RrdtoolInterface $rrd,
        private readonly LabelStrategy $labelStrategy,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function write(MetricSample $sample): void
    {
        $this->logger->debug('Writing to RRD', ['sample' => $sample]);

        $filename = $this->labelStrategy->generateFilename($sample->metric, $sample->labels);
        $timestamp = $sample->timestamp?->getTimestamp();
        try {
            $this->rrd->update($filename, [$sample->value], $timestamp);
        } catch (RrdNotFoundException) {
            $this->createRrd($sample, $filename);
            $this->rrd->update($filename, [$sample->value], $timestamp);
        }
    }

    public function writeBatch(array $samples): void
    {
        foreach ($samples as $sample) {
            $this->write($sample);
        }
    }

    private function createRrd(MetricSample $sample, string $filename): void
    {
        $metricId = $sample->metric;
        if (! $this->metrics->has($metricId->key())) {
            throw new UnknownMetricException('Unknown metric: '.$metricId->key());
        }

        $directory = dirname($this->config->dir.'/'.$filename); // FIXME is this right?

        if (! $this->config->rrdcached && ! is_dir($directory)) {
            mkdir($directory, recursive: true);
        }

        $policies = $metricId->retentionPolicies
            ?: $this->config->defaultRetentionPolicies;

        // TODO throw exception if policies are not ascending

        // TODO evaluate
        $datasets = [
            'value' => $metricId->type ?? \TimeseriesPhp\Core\Enum\MetricType::GAUGE,
        ];

        $this->rrd->create($filename, $datasets, $policies);
    }
}
