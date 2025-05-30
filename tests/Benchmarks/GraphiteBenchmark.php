<?php

namespace TimeSeriesPhp\Tests\Benchmarks;

use TimeSeriesPhp\Drivers\Graphite\Config\GraphiteConfig;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;

/**
 * Benchmark tests for Graphite driver.
 *
 * @group benchmark
 */
class GraphiteBenchmark extends AbstractDriverBenchmark
{
    private GraphiteConfig $config;

    protected function setUpDriver(): void
    {
        // Skip test if sockets are not available
        if (! function_exists('socket_create')) {
            $this->markTestSkipped('socket_create function is not available');
        }

        // Try to connect to Graphite
        $graphiteHost = getenv('GRAPHITE_HOST') ?: 'localhost';
        $graphitePort = getenv('GRAPHITE_PORT') ?: 2003; // Default Carbon port
        $graphiteQueryPort = getenv('GRAPHITE_QUERY_PORT') ?: 8080; // Default web port

        // Create a real GraphiteConfig
        $this->config = new GraphiteConfig([
            'host' => $graphiteHost,
            'port' => $graphitePort,
            'query_port' => $graphiteQueryPort,
            'protocol' => 'tcp', // Use TCP for testing
            'timeout' => 30, // Longer timeout for benchmarks
            'prefix' => 'benchmark.test',
            'debug' => false,
        ]);

        // Create a real Graphite Driver
        $this->driver = new GraphiteDriver;

        try {
            $connected = $this->driver->connect($this->config);
            if (! $connected) {
                $this->markTestSkipped('Could not connect to Graphite at '.$graphiteHost.':'.$graphitePort);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not connect to Graphite: '.$e->getMessage());
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
     * Override the query benchmark to use Graphite-specific queries.
     */
    public function test_benchmark_query(): void
    {
        if (! $this->driver->isConnected()) {
            $this->markTestSkipped('Driver is not connected');
        }

        // First write some data to query
        $dataPoints = [];
        for ($i = 0; $i < 100; $i++) {
            $dataPoints[] = new \TimeSeriesPhp\Core\Data\DataPoint(
                $this->measurement,
                ['value' => rand(1, 100) / 10],
                ['host' => 'benchmark-query-host'],
                (new \DateTime)->modify("-{$i} seconds")
            );
        }

        $this->driver->writeBatch($dataPoints);

        // Wait a moment for data to be available
        sleep(1);

        $start = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            // Use a Graphite query that should work with the data we just wrote
            $target = 'benchmark.test.'.$this->measurement.'.benchmark-query-host.value';
            $from = '-5min';

            $graphiteQuery = 'target='.$target.'&from='.$from;

            $rawQuery = new \TimeSeriesPhp\Core\Query\RawQuery($graphiteQuery);
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
