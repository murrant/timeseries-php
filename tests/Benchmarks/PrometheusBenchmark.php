<?php

namespace TimeSeriesPhp\Tests\Benchmarks;

use TimeSeriesPhp\Drivers\Prometheus\Config\PrometheusConfig;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;

/**
 * Benchmark tests for Prometheus driver.
 *
 * @group benchmark
 */
class PrometheusBenchmark extends AbstractDriverBenchmark
{
    private PrometheusConfig $config;

    protected function setUpDriver(): void
    {
        // Skip test if curl extension is not available
        if (! extension_loaded('curl')) {
            $this->markTestSkipped('curl extension is not available');
        }

        // Try to connect to Prometheus
        $prometheusUrl = getenv('PROMETHEUS_URL') ?: 'http://localhost:9090';
        $timeout = getenv('PROMETHEUS_TIMEOUT') ?: 30;

        // Create a real PrometheusConfig
        $this->config = new PrometheusConfig([
            'url' => $prometheusUrl,
            'timeout' => $timeout,
            'verify_ssl' => false, // Don't verify SSL for testing
            'debug' => false,
        ]);

        // Create a real Prometheus Driver
        $this->driver = new PrometheusDriver;

        try {
            $connected = $this->driver->connect($this->config);
            if (! $connected) {
                $this->markTestSkipped('Could not connect to Prometheus at '.$prometheusUrl);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not connect to Prometheus: '.$e->getMessage());
        }
    }

    protected function tearDownDriver(): void
    {
        // Close the driver connection
        if ($this->driver->isConnected()) {
            $this->driver->close();
        }
    }

    /**
     * Override the single write benchmark since Prometheus doesn't support direct writes.
     */
    public function test_benchmark_single_write(): void
    {
        $this->markTestSkipped('Prometheus does not support direct writes');
    }

    /**
     * Override the batch write benchmark since Prometheus doesn't support direct writes.
     */
    public function test_benchmark_batch_write(): void
    {
        $this->markTestSkipped('Prometheus does not support direct writes');
    }

    /**
     * Override the query benchmark to use Prometheus-specific queries.
     */
    public function test_benchmark_query(): void
    {
        if (! $this->driver->isConnected()) {
            $this->markTestSkipped('Driver is not connected');
        }

        $start = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            // Use a simple query that should work on any Prometheus instance
            $promqlQuery = 'up';

            $rawQuery = new \TimeSeriesPhp\Core\Query\RawQuery($promqlQuery);
            $this->driver->rawQuery($rawQuery);
        }

        $end = microtime(true);
        $duration = $end - $start;
        $queriesPerSecond = 10 / $duration;

        $this->addToAssertionCount(1); // Count this as a passed assertion

        echo sprintf(
            "Query benchmark: 10 queries in %.4f seconds (%.2f queries/sec)\n",
            $duration,
            $queriesPerSecond
        );
    }
}
