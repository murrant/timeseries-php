<?php

namespace TimeSeriesPhp\Tests\Drivers\Prometheus;

use DateTime;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\RawQuery;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;

class PrometheusDriverTest extends TestCase
{
    private PrometheusDriver $driver;

    private PrometheusConfig $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(PrometheusConfig::class);

        // Configure the mock to return expected values
        $this->config->method('get')
            ->willReturnMap([
                ['url', null, 'http://localhost:9090'],
                ['timeout', null, 30],
                ['verify_ssl', null, true],
                ['debug', null, false],
            ]);

        $this->config->method('getClientConfig')
            ->willReturn([
                'url' => 'http://localhost:9090',
                'timeout' => 30,
                'verify_ssl' => true,
                'debug' => false,
            ]);

        // Create a mock of the Guzzle Client
        $mockClient = $this->createMock(Client::class);

        // Sample response for status/config endpoint (used in connect)
        $configResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['some' => 'config'],
        ]) ?: json_last_error_msg());

        // Sample response for query endpoint
        $queryResponse = new Response(200, [], json_encode([
            'status' => 'success',
            'data' => [
                'result' => [
                    [
                        'metric' => ['__name__' => 'cpu_usage', 'instance' => 'localhost:9090'],
                        'value' => [time(), '0.75'],
                    ],
                    [
                        'metric' => ['__name__' => 'cpu_usage', 'instance' => 'localhost:9091'],
                        'value' => [time(), '0.85'],
                    ],
                ],
            ],
        ]) ?: json_last_error_msg());

        // Configure the mock client to return appropriate responses
        $mockClient->method('get')
            ->willReturnCallback(function ($url, $options) use ($configResponse, $queryResponse) {
                if (strpos($url, '/api/v1/status/config') !== false) {
                    return $configResponse;
                }

                return $queryResponse;
            });

        // Create the real driver (not a mock)
        $this->driver = new PrometheusDriver;

        // Inject the mock client
        $this->driver->setClient($mockClient);

        // Connect the driver
        $this->driver->connect($this->config);
    }

    public function test_connect(): void
    {
        $result = $this->driver->isConnected();
        $this->assertTrue($result);
    }

    public function test_query(): void
    {
        $query = new Query('cpu_usage');
        $query->select(['value'])
            ->where('instance', '=', 'localhost:9090')
            ->timeRange(new DateTime('2023-01-01'), new DateTime('2023-01-02'));

        $result = $this->driver->query($query);

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertCount(2, $result->getSeries());
    }

    public function test_raw_query(): void
    {
        $rawQuery = new RawQuery('cpu_usage{instance="localhost:9090"}');
        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertCount(2, $result->getSeries());
    }

    public function test_write(): void
    {
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['value' => 23.5],
            ['instance' => 'localhost:9090'],
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
                ['value' => 23.5],
                ['instance' => 'localhost:9090']
            ),
            new DataPoint(
                'cpu_usage',
                ['value' => 25.0],
                ['instance' => 'localhost:9091']
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
        $databases = $this->driver->listDatabases();
        $this->assertEmpty($databases);
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
