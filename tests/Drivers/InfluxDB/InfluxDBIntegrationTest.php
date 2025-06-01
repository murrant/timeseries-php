<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Core\Query\RawQuery;
use TimeSeriesPhp\Drivers\InfluxDB\Config\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;

/**
 * Integration test for InfluxDBDriver that assumes InfluxDB is available
 * and can be connected to with the provided configuration.
 *
 * @group integration
 */
class InfluxDBIntegrationTest extends TestCase
{
    private InfluxDBDriver $driver;

    private InfluxDBConfig $config;

    private string $testBucket = 'test_integration';

    protected function setUp(): void
    {
        // Skip test if curl extension is not available
        if (! extension_loaded('curl')) {
            $this->markTestSkipped('curl extension is not available');
        }

        // Try to connect to InfluxDB
        $influxUrl = getenv('INFLUXDB_URL') ?: 'http://localhost:8086';
        $influxToken = getenv('INFLUXDB_TOKEN') ?: 'my-token';
        $influxOrg = getenv('INFLUXDB_ORG') ?: 'my-org';
        $influxBucket = getenv('INFLUXDB_BUCKET') ?: $this->testBucket;

        // Create a real InfluxDBConfig
        $this->config = new InfluxDBConfig([
            'url' => $influxUrl,
            'token' => $influxToken,
            'org' => $influxOrg,
            'bucket' => $influxBucket,
            'timeout' => 5, // Short timeout for testing
            'verify_ssl' => false, // Don't verify SSL for testing
            'debug' => false,
        ]);

        // Create a real InfluxDBDriver with the client and query builder
        $client = new \InfluxDB2\Client([
            'url' => $influxUrl,
            'token' => $influxToken,
            'org' => $influxOrg,
            'bucket' => $influxBucket,
            'timeout' => 5,
            'verifySSL' => false,
            'debug' => false,
        ]);
        $queryBuilder = new \TimeSeriesPhp\Drivers\InfluxDB\Query\QueryBuilder;
        $this->driver = new InfluxDBDriver($client, $queryBuilder);

        // Configure the driver
        $this->driver->configure($this->config->getConfig());

        try {
            $connected = $this->driver->connect();
            if (! $connected) {
                $this->markTestSkipped('Could not connect to InfluxDB at '.$influxUrl);
            }

            // Create test bucket if it doesn't exist
            try {
                $this->driver->createDatabase($this->testBucket);
            } catch (\Exception $e) {
                // Bucket might already exist, that's fine
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not connect to InfluxDB: '.$e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        // Close the driver connection
        if ($this->driver->isConnected()) {
            // Clean up test data
            try {
                $now = new DateTime;
                $pastDay = (new DateTime)->modify('-1 day');
                $this->driver->deleteMeasurement('cpu_usage', $pastDay, $now);
                $this->driver->deleteMeasurement('memory_usage', $pastDay, $now);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }

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
            ['usage_user' => 23.5, 'usage_system' => 12.1],
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
                ['usage_user' => 23.5],
                ['host' => 'server01']
            ),
            new DataPoint(
                'cpu_usage',
                ['usage_user' => 25.0],
                ['host' => 'server02']
            ),
            new DataPoint(
                'memory_usage',
                ['used_percent' => 75.2],
                ['host' => 'server01']
            ),
        ];

        $result = $this->driver->writeBatch($dataPoints);
        $this->assertTrue($result);
    }

    public function test_query(): void
    {
        // First write some data
        $timestamp = new DateTime;
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['usage_user' => 42.5],
            ['host' => 'query-test-server'],
            $timestamp
        );
        $this->driver->write($dataPoint);

        // Wait a moment for data to be available
        sleep(1);

        // Query the data
        $query = new Query('cpu_usage');
        $query->select(['usage_user'])
            ->where('host', '=', 'query-test-server')
            ->timeRange(
                (clone $timestamp)->modify('-1 minute'),
                (clone $timestamp)->modify('+1 minute')
            );

        $result = $this->driver->query($query);

        $this->assertInstanceOf(QueryResult::class, $result);
        $series = $result->getSeries();
        $this->assertNotEmpty($series);
    }

    public function test_raw_query(): void
    {
        // First write some data
        $timestamp = new DateTime;
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['usage_user' => 33.3],
            ['host' => 'raw-query-test-server'],
            $timestamp
        );
        $this->driver->write($dataPoint);

        // Wait a moment for data to be available
        sleep(1);

        // Use Flux query language with a hardcoded bucket name for the test
        // This avoids phpstan issues with mixed types
        $fluxQuery = 'from(bucket:"'.$this->testBucket.'") '.
                    '|> range(start: -1h) '.
                    '|> filter(fn: (r) => r._measurement == "cpu_usage" and r.host == "raw-query-test-server")';

        $rawQuery = new RawQuery($fluxQuery);
        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
    }

    public function test_create_database(): void
    {
        $testDb = 'integration_test_'.uniqid();
        $result = $this->driver->createDatabase($testDb);
        $this->assertTrue($result);
    }

    public function test_list_databases(): void
    {
        $databases = $this->driver->getDatabases();
        $this->assertNotEmpty($databases, 'InfluxDB should return a non-empty list of databases');
    }

    public function test_health(): void
    {
        $health = $this->driver->getHealth();
        $this->assertArrayHasKey('status', $health, 'Health check should return an array with a status key');
    }
}
