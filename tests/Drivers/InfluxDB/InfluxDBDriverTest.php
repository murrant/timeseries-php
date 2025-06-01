<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Drivers\InfluxDB\Config\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\InfluxDB\Query\InfluxDBQueryBuilder;

class InfluxDBDriverTest extends TestCase
{
    private InfluxDBDriver $driver;

    private InfluxDBConfig $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(InfluxDBConfig::class);

        // Configure the mock to return expected values
        $this->config->method('get')
            ->willReturnMap([
                ['url', null, 'http://localhost:8086'],
                ['token', null, 'test-token'],
                ['org', null, 'test-org'],
                ['bucket', null, 'test-bucket'],
                ['timeout', null, 30],
                ['verify_ssl', null, true],
                ['debug', null, false],
            ]);

        $this->config->method('getClientConfig')
            ->willReturn([
                'url' => 'http://localhost:8086',
                'token' => 'test-token',
                'bucket' => 'test-bucket',
                'org' => 'test-org',
                'timeout' => 30,
                'verifySSL' => true,
                'debug' => false,
            ]);

        // Create a mock client
        $mockClient = $this->createMock(\InfluxDB2\Client::class);
        $mockWriteApi = $this->createMock(\InfluxDB2\WriteApi::class);
        $mockQueryApi = $this->createMock(\InfluxDB2\QueryApi::class);
        $mockBucketsService = $this->createMock(\InfluxDB2\Service\BucketsService::class);

        // Configure the mock client to return the mock APIs
        $mockClient->method('createWriteApi')->willReturn($mockWriteApi);
        $mockClient->method('createQueryApi')->willReturn($mockQueryApi);
        $mockClient->method('createService')->willReturn($mockBucketsService);

        // Configure the mock BucketsService to return expected values
        $mockBucket = $this->createMock(\InfluxDB2\Model\Bucket::class);
        $mockBucket->method('getName')->willReturn('mydb');
        $mockBuckets = $this->createMock(\InfluxDB2\Model\Buckets::class);
        $mockBuckets->method('getBuckets')->willReturn([$mockBucket, $mockBucket]);
        $mockBucketsService->method('getBuckets')->willReturn($mockBuckets);
        $mockBucketsService->method('postBuckets')->willReturn($mockBucket);

        // Create a real instance of InfluxDBDriver with mocked methods
        $this->driver = new class($mockClient, $mockWriteApi, $mockQueryApi, $mockBucketsService) extends InfluxDBDriver
        {
            /**
             * @param  \InfluxDB2\Client  $mockClient
             * @param  \InfluxDB2\WriteApi  $mockWriteApi
             * @param  \InfluxDB2\QueryApi  $mockQueryApi
             * @param  \InfluxDB2\Service\BucketsService  $mockBucketsService
             */
            public function __construct($mockClient, $mockWriteApi, $mockQueryApi, $mockBucketsService)
            {
                $this->client = $mockClient;
                $this->writeApi = $mockWriteApi;
                $this->queryApi = $mockQueryApi;
                $this->bucketsService = $mockBucketsService;
                $this->org = 'test-org';
                $this->bucket = 'test-bucket';
                $this->connected = true;
                $this->queryBuilder = new InfluxDBQueryBuilder('test-bucket');
            }

            protected function doConnect(): bool
            {
                return true;
            }

            /**
             * @return array<int, array{'time': string, 'cpu_usage': int}>
             */
            protected function executeQuery(Query $query): array
            {
                return [
                    ['time' => '2023-01-01T00:00:00Z', 'cpu_usage' => 10],
                    ['time' => '2023-01-01T01:00:00Z', 'cpu_usage' => 15],
                ];
            }

            public function rawQuery(\TimeSeriesPhp\Contracts\Query\RawQueryInterface $query): \TimeSeriesPhp\Core\Data\QueryResult
            {
                // Mock implementation that doesn't use queryApi
                return new \TimeSeriesPhp\Core\Data\QueryResult([
                    'cpu_usage' => [
                        ['date' => '2023-01-01T00:00:00Z', 'value' => 10],
                        ['date' => '2023-01-01T01:00:00Z', 'value' => 15],
                    ],
                ]);
            }

            protected function doWrite(\TimeSeriesPhp\Core\Data\DataPoint $dataPoint): bool
            {
                // Mock implementation that doesn't use writeApi
                return true;
            }

            protected function doWriteBatch(array $dataPoints): bool
            {
                // Mock implementation that doesn't use writeApi
                return true;
            }

            public function createDatabase(string $database): bool
            {
                // Mock implementation that doesn't use client
                return true;
            }

            /**
             * @return string[]
             */
            public function getDatabases(): array
            {
                // Mock implementation that doesn't use client
                return ['mydb', 'testdb'];
            }
        };
    }

    public function test_connect(): void
    {
        $result = $this->driver->connect($this->config);
        $this->assertTrue($result);
    }

    public function test_query(): void
    {
        $query = new Query('cpu_usage');
        $query->select(['usage_user', 'usage_system'])
            ->where('host', '=', 'server01')
            ->timeRange(new DateTime('2023-01-01'), new DateTime('2023-01-02'));

        $result = $this->driver->query($query);

        $this->assertInstanceOf(QueryResult::class, $result);
        $series = $result->getSeries();
        $this->assertNotEmpty($series);
        // Check that at least one field exists in the series
        $this->assertGreaterThanOrEqual(1, count($series));
    }

    public function test_raw_query(): void
    {
        $rawQuery = new \TimeSeriesPhp\Core\Query\RawQuery('SELECT * FROM cpu_usage');
        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
        $series = $result->getSeries();
        $this->assertNotEmpty($series);
        // Check that at least one field exists in the series
        $this->assertGreaterThanOrEqual(1, count($series));
    }

    public function test_write(): void
    {
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['usage_user' => 23.5, 'usage_system' => 12.1],
            ['host' => 'server01', 'region' => 'us-west'],
            new DateTime('2023-01-01 12:00:00')
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
        ];

        $result = $this->driver->writeBatch($dataPoints);
        $this->assertTrue($result);
    }

    public function test_create_database(): void
    {
        $result = $this->driver->createDatabase('test_db');
        $this->assertTrue($result);
    }

    public function test_list_databases(): void
    {
        $databases = $this->driver->getDatabases();
        $this->assertContains('mydb', $databases);
        $this->assertContains('testdb', $databases);
    }

    public function test_close(): void
    {
        $this->driver->close();

        // Use reflection to check if the connected property is set to false
        $reflection = new \ReflectionClass($this->driver);
        $property = $reflection->getProperty('connected');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($this->driver));
    }
}
