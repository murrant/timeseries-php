<?php

namespace TimeSeriesPhp\Tests\Drivers\Prometheus;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\RawQuery;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusQueryBuilder;

/**
 * Integration test for Prometheus driver that assumes Prometheus is available
 * and can be connected to with the provided configuration.
 *
 * @group integration
 */
class PrometheusIntegrationTest extends TestCase
{
    private PrometheusDriver $driver;

    protected function setUp(): void
    {
        // Skip test if curl extension is not available
        if (! extension_loaded('curl')) {
            $this->markTestSkipped('curl extension is not available');
        }

        // Try to connect to Prometheus
        $prometheusUrl = getenv('PROMETHEUS_URL') ?: 'http://localhost:9090';
        $timeout = getenv('PROMETHEUS_TIMEOUT') ?: 5;

        // Create a real PrometheusConfig
        $config = new PrometheusConfig(
            url: $prometheusUrl,
            timeout: (int) $timeout,
            verify_ssl: false, // Don't verify SSL for testing
            debug: false,
        );

        $httpFactory = new HttpFactory;

        // Create a real Prometheus Driver
        $this->driver = new PrometheusDriver(new Client, $httpFactory, $httpFactory, $config, new PrometheusQueryBuilder, new NullLogger);

        try {
            $connected = $this->driver->connect();
            if (! $connected) {
                $this->markTestSkipped('Could not connect to Prometheus at '.$prometheusUrl);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not connect to Prometheus: '.$e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Close the driver connection
        if ($this->driver->isConnected()) {
            $this->driver->close();
        }
    }

    public function test_connect(): void
    {
        $this->assertTrue($this->driver->isConnected());
    }

    public function test_raw_query_instant(): void
    {
        // Use a simple query that should work on any Prometheus instance
        $promqlQuery = 'up'; // The "up" metric shows if targets are up

        $rawQuery = new RawQuery($promqlQuery);
        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
        // We don't assert on the content because it depends on the Prometheus setup
    }

    public function test_raw_query_range(): void
    {
        // Use a range query with time parameters
        $now = time();
        $fiveMinutesAgo = $now - 300;

        $promqlQuery = 'up # time range: '.$fiveMinutesAgo.' to '.$now;

        $rawQuery = new RawQuery($promqlQuery);
        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
        // We don't assert on the content because it depends on the Prometheus setup
    }

    public function test_raw_query_relative(): void
    {
        // Use a query with relative time
        $promqlQuery = 'up # relative time: 5m';

        $rawQuery = new RawQuery($promqlQuery);
        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
        // We don't assert on the content because it depends on the Prometheus setup
    }

    public function test_list_databases(): void
    {
        // Prometheus doesn't have the concept of databases, but the method should return an empty array
        $databases = $this->driver->getDatabases();
        $this->assertEmpty($databases, 'Prometheus should return an empty array of databases');
    }

    public function test_create_database(): void
    {
        // Prometheus doesn't support creating databases, but the method should return true
        $result = $this->driver->createDatabase('test_db');
        $this->assertTrue($result);
    }
}
