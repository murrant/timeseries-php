<?php

namespace TimeseriesPhp\Core\Metrics\Repository;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;
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
        $searchPath = str_ends_with($path, '.yaml') ? $path : "$path/*.yaml";
        $files = glob($searchPath) ?: [];

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
        $raw = Yaml::parseFile($file); // FIXME we don't want this dependency here

        if ($raw === false) {
            $this->logger->error('Failed to parse YAML file', ['file' => $file]);

            return;
        }

        foreach ($raw as $namespace => $metrics) {
            foreach ($metrics as $metric => $definition) {
                $definition['namespace'] = $namespace;
                $definition['name'] = $metric;

                $metricIdentifier = MetricIdentifier::fromArray($definition);

                $this->metrics[$metricIdentifier->key()] = $metricIdentifier;
            }
        }
    }
}
