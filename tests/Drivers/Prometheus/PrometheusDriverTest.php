<?php

namespace TimeSeriesPhp\Tests\Drivers\Prometheus;

use DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Core\Query\RawQuery;
use TimeSeriesPhp\Drivers\Prometheus\Config\PrometheusConfig;
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

        // Create mocks for PSR interfaces
        $mockClient = $this->createMock(ClientInterface::class);
        $mockRequestFactory = $this->createMock(RequestFactoryInterface::class);
        $mockUriFactory = $this->createMock(UriFactoryInterface::class);
        $mockStreamFactory = $this->createMock(StreamFactoryInterface::class);
        $mockRequest = $this->createMock(RequestInterface::class);
        $mockUri = $this->createMock(UriInterface::class);
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockStream = $this->createMock(StreamInterface::class);

        // Configure URI factory mock
        $mockUriFactory->method('createUri')
            ->willReturn($mockUri);

        $mockUri->method('withQuery')
            ->willReturnSelf();

        $mockUri->method('__toString')
            ->willReturnCallback(function () {
                return 'http://localhost:9090/api/v1/query';
            });

        // Configure request factory mock
        $mockRequestFactory->method('createRequest')
            ->willReturn($mockRequest);

        // Configure stream factory and stream mocks
        $mockStreamFactory->method('createStream')
            ->willReturn($mockStream);

        // Configure response body for different endpoints
        $configResponseBody = json_encode([
            'status' => 'success',
            'data' => ['some' => 'config'],
        ]) ?: json_last_error_msg();

        $queryResponseBody = json_encode([
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
        ]) ?: json_last_error_msg();

        // Configure stream mock to return appropriate response body
        $mockStream->method('__toString')
            ->willReturnCallback(function () use ($queryResponseBody) {
                // In a real implementation, we would check the request URI to determine which response to return
                // For simplicity, we'll just return the query response for all requests
                return $queryResponseBody;
            });

        // Configure response mock
        $mockResponse->method('getStatusCode')
            ->willReturn(200);

        $mockResponse->method('getBody')
            ->willReturn($mockStream);

        // Configure client mock to return the response
        $mockClient->method('sendRequest')
            ->willReturn($mockResponse);

        // Create the real driver (not a mock)
        $this->driver = new PrometheusDriver;

        // Inject the mocks
        $this->driver->setClient($mockClient);
        $this->driver->setRequestFactory($mockRequestFactory);
        $this->driver->setUriFactory($mockUriFactory);
        $this->driver->setStreamFactory($mockStreamFactory);

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
        $series = $result->getSeries();
        $this->assertNotEmpty($series);
        // Check that at least one field exists in the series
        $this->assertGreaterThanOrEqual(1, count($series));
    }

    public function test_raw_query(): void
    {
        $rawQuery = new RawQuery('cpu_usage{instance="localhost:9090"}');
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
        $databases = $this->driver->getDatabases();
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
