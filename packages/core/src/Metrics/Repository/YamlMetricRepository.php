<?php

namespace TimeseriesPhp\Core\Metrics\Repository;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Exceptions\UnknownMetricException;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;

final class YamlMetricRepository implements MetricRepository
{
    /** @var array<string, MetricIdentifier> */
    private array $metrics = [];

    public function __construct(
        string $path,
        private readonly LoggerInterface $logger = new NullLogger
    ) {
        $files = glob("{$path}/*.yaml") ?: [];
        foreach ($files as $file) {
            $this->loadFile($file);
        }
    }

    public function get(string $key): MetricIdentifier
    {
        if (! isset($this->metrics[$key])) {
            throw new UnknownMetricException($key);
        }

        return $this->metrics[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->metrics[$key]);
    }

    public function all(): iterable
    {
        return $this->metrics;
    }

    public function register(MetricIdentifier $metric): void
    {
        $this->metrics[$metric->key()] = $metric;
    }

    private function loadFile(string $file): void
    {
        $this->logger->debug('Loading metrics from YAML file', ['file' => $file]);
        $raw = yaml_parse_file($file);

        if ($raw === false) {
            $this->logger->error('Failed to parse YAML file', ['file' => $file]);

            return;
        }

        foreach ($raw as $key => $definition) {
            $this->metrics[$key] = $this->parseMetric(
                $key,
                $definition,
                $file
            );
        }
    }

    private function parseMetric(
        string $namespace,
        string $name,
        array $raw
    ): MetricIdentifier {
        return new MetricIdentifier(
            namespace: $namespace,
            name: $name,
            type: MetricType::from($raw['type']),
            unit: $raw['unit'] ?? null,
            labels: $raw['labels'] ?? [],
            aggregations: $raw['aggregations'] ?? [],
        );
    }
}
