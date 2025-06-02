<?php

namespace TimeSeriesPhp\Tests\Drivers\Graphite;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\RawQuery;
use TimeSeriesPhp\Drivers\Graphite\GraphiteConfig;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;

/**
 * Integration test for Graphite driver that assumes Graphite is available
 * and can be connected to with the provided configuration.
 *
 * @group integration
 */
class GraphiteIntegrationTest extends TestCase
{
    private GraphiteDriver $driver;

    private GraphiteConfig $config;

    protected function setUp(): void
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
            'timeout' => 5, // Short timeout for testing
            'prefix' => 'test.integration',
            'debug' => false,
        ]);

        // Create a real Graphite Driver
        $this->driver = new GraphiteDriver;

        try {
            $connected = $this->driver->connect();
            if (! $connected) {
                $this->markTestSkipped('Could not connect to Graphite at '.$graphiteHost.':'.$graphitePort);
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not connect to Graphite: '.$e->getMessage());
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

    public function test_write(): void
    {
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['value' => 23.5],
            ['host' => 'server01', 'region' => 'us-west'],
            new DateTime
        );

        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);
    }

    public function test_write_batch(): void
    {
        $dataPoints = [
            new DataPoint(
                'cpu_usage',
                ['value' => 23.5],
                ['host' => 'server01']
            ),
            new DataPoint(
                'cpu_usage',
                ['value' => 25.0],
                ['host' => 'server02']
            ),
            new DataPoint(
                'memory_usage',
                ['value' => 75.2],
                ['host' => 'server01']
            ),
        ];

        $result = $this->driver->writeBatch($dataPoints);
        $this->assertTrue($result);
    }

    public function test_raw_query(): void
    {
        // First write some data
        $timestamp = new DateTime;
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['value' => 33.3],
            ['host' => 'raw-query-test-server'],
            $timestamp
        );
        $this->driver->write($dataPoint);

        // Wait a moment for data to be available
        sleep(1);

        // Use Graphite query language
        $target = 'test.integration.cpu_usage.raw-query-test-server.value';
        $from = '-5min';

        $graphiteQuery = 'target='.$target.'&from='.$from;

        $rawQuery = new RawQuery($graphiteQuery);
        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
    }

    public function test_create_database(): void
    {
        // Graphite doesn't have the concept of creating databases, but the method should return true
        $result = $this->driver->createDatabase('test_db');
        $this->assertTrue($result);
    }

    public function test_list_databases(): void
    {
        // Graphite doesn't have the concept of databases, but the method should return an empty array
        $databases = $this->driver->getDatabases();
        $this->assertEmpty($databases, 'Graphite should return an empty array of databases');
    }
}
