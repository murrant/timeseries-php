<?php

namespace TimeSeriesPhp\Tests\Benchmarks;

use TimeSeriesPhp\Drivers\InfluxDB\Config\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;

/**
 * Benchmark tests for InfluxDB driver.
 *
 * @group benchmark
 */
class InfluxDBBenchmark extends AbstractDriverBenchmark
{
    private InfluxDBConfig $config;

    protected function setUpDriver(): void
    {
        // Skip test if curl extension is not available
        if (! extension_loaded('curl')) {
            $this->markTestSkipped('curl extension is not available');
        }

        // Try to connect to InfluxDB
        $influxUrl = getenv('INFLUXDB_URL') ?: 'http://localhost:8086';
        $influxToken = getenv('INFLUXDB_TOKEN') ?: 'my-token';
        $influxOrg = getenv('INFLUXDB_ORG') ?: 'my-org';
        $influxBucket = getenv('INFLUXDB_BUCKET') ?: 'benchmark_test';

        // Create a real InfluxDBConfig
        $this->config = new InfluxDBConfig([
            'url' => $influxUrl,
            'token' => $influxToken,
            'org' => $influxOrg,
            'bucket' => $influxBucket,
            'timeout' => 30, // Longer timeout for benchmarks
            'verify_ssl' => false, // Don't verify SSL for testing
            'debug' => false,
        ]);

        // Create a real InfluxDBDriver
        $this->driver = new InfluxDBDriver;

        try {
            $connected = $this->driver->connect($this->config);
            if (! $connected) {
                $this->markTestSkipped('Could not connect to InfluxDB at '.$influxUrl);
            }

            // Create benchmark bucket if it doesn't exist
            try {
                $this->driver->createDatabase('benchmark_test');
            } catch (\Exception $e) {
                // Bucket might already exist, that's fine
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not connect to InfluxDB: '.$e->getMessage());
        }
    }

    protected function tearDownDriver(): void
    {
        // Clean up test data
        if ($this->driver->isConnected()) {
            try {
                $now = new \DateTime;
                $pastDay = (new \DateTime)->modify('-1 day');
                $this->driver->deleteMeasurement($this->measurement, $pastDay, $now);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }

            $this->driver->close();
        }
    }
}
