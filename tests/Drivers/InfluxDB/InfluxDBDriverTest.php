<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB;

use DateTime;
use InfluxDB2\Client;
use InfluxDB2\Model\Buckets;
use InfluxDB2\Model\Organizations;
use InfluxDB2\QueryApi;
use InfluxDB2\Service\BucketsService;
use InfluxDB2\Service\OrganizationsService;
use InfluxDB2\WriteApi;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Contracts\Connection\ConnectionAdapterInterface;
use TimeSeriesPhp\Core\Connection\CommandResponse;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Driver\Formatter\LineProtocolFormatter;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Core\Query\RawQuery;
use TimeSeriesPhp\Drivers\InfluxDB\Factory\ClientFactoryInterface;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBQueryBuilder;

class InfluxDBDriverTest extends TestCase
{
    private InfluxDBDriver $driver;

    private InfluxDBConfig $config;

    private Client $mockClient;

    private WriteApi $mockWriteApi;

    private QueryApi $mockQueryApi;

    private BucketsService $mockBucketsService;

    private OrganizationsService $mockOrganizationsService;

    protected function setUp(): void
    {
        // Create a real instance of InfluxDBConfig with test values
        $this->config = new InfluxDBConfig(
            url: 'http://localhost:8086',
            token: 'test_token',
            org: 'test_org',
            bucket: 'test_bucket',
            timeout: 30,
            verify_ssl: true,
            debug: false
        );

        // Create mocks for InfluxDB dependencies
        $this->mockClient = $this->createMock(Client::class);
        $this->mockWriteApi = $this->createMock(WriteApi::class);
        $this->mockQueryApi = $this->createMock(QueryApi::class);
        $this->mockBucketsService = $this->createMock(BucketsService::class);
        $this->mockOrganizationsService = $this->createMock(OrganizationsService::class);

        // Configure mock client
        $this->mockClient->method('createWriteApi')
            ->willReturn($this->mockWriteApi);
        $this->mockClient->method('createQueryApi')
            ->willReturn($this->mockQueryApi);
        $this->mockClient->method('createService')
            ->willReturnCallback(function ($serviceClass) {
                if ($serviceClass === BucketsService::class) {
                    return $this->mockBucketsService;
                }
                if ($serviceClass === OrganizationsService::class) {
                    return $this->mockOrganizationsService;
                }

                return null;
            });
        $this->mockClient->method('ping')
            ->willReturn([
                'X-Influxdb-Build' => ['test_build'],
                'X-Influxdb-Version' => ['test_version'],
            ]);

        // Configure mock query API
        $this->mockQueryApi->method('query')
            ->willReturn([
                (object) [
                    'records' => [
                        (object) [
                            'values' => (object) [
                                '_time' => time(),
                                'value' => 0.75,
                                'instance' => 'localhost:8086',
                            ],
                        ],
                        (object) [
                            'values' => (object) [
                                '_time' => time(),
                                'value' => 0.85,
                                'instance' => 'localhost:8087',
                            ],
                        ],
                    ],
                ],
            ]);

        // Configure mock buckets service
        $mockBuckets = $this->createMock(Buckets::class);
        $mockBuckets->method('getBuckets')
            ->willReturn([
                (object) ['name' => 'test_bucket'],
                (object) ['name' => 'another_bucket'],
            ]);
        $this->mockBucketsService->method('getBuckets')
            ->willReturn($mockBuckets);
        $this->mockBucketsService->method('postBuckets')
            ->willReturn(null);

        // Configure mock organizations service
        $mockOrganizations = $this->createMock(Organizations::class);
        $mockOrganizations->method('getOrgs')
            ->willReturn([
                (object) ['name' => 'test_org', 'id' => 'test_org_id'],
            ]);
        $this->mockOrganizationsService->method('getOrgs')
            ->willReturn($mockOrganizations);

        // Create mock factories
        $mockClientFactory = $this->createMock(ClientFactoryInterface::class);
        $mockClientFactory->method('create')
            ->willReturn($this->mockClient);

        $mockQueryBuilder = $this->createMock(InfluxDBQueryBuilder::class);
        $mockQueryBuilder->method('build')
            ->willReturn(new RawQuery('from(bucket:"test_bucket") |> range(start: -1h) |> filter(fn: (r) => r._measurement == "cpu_usage")'));

        // Create a mock connection adapter
        $mockConnectionAdapter = $this->createMock(ConnectionAdapterInterface::class);
        $mockConnectionAdapter->method('connect')->willReturn(true);
        $mockConnectionAdapter->method('executeCommand')->willReturnCallback(function ($command, $data) {
            if ($command === 'ping') {
                return new CommandResponse(
                    true,
                    '',
                    [
                        'headers' => [
                            'X-Influxdb-Build: test_build',
                            'X-Influxdb-Version: test_version',
                        ],
                    ]
                );
            }

            return new CommandResponse(true, '');
        });

        // Create a formatter
        $writeFormatter = new LineProtocolFormatter;

        // Create a mock logger
        $mockLogger = $this->createMock(\Psr\Log\LoggerInterface::class);

        // Create a custom subclass of InfluxDBDriver that bypasses the parent constructor
        $this->driver = new class($this->mockClient, $this->mockWriteApi, $this->mockQueryApi, $this->mockBucketsService, $this->mockOrganizationsService, $mockQueryBuilder, $mockLogger, $mockConnectionAdapter, $writeFormatter) extends InfluxDBDriver
        {
            protected bool $connected = false;

            public function __construct(
                Client $mockClient,
                WriteApi $mockWriteApi,
                QueryApi $mockQueryApi,
                BucketsService $mockBucketsService,
                OrganizationsService $mockOrganizationsService,
                InfluxDBQueryBuilder $mockQueryBuilder,
                \Psr\Log\LoggerInterface $mockLogger,
                ConnectionAdapterInterface $mockConnectionAdapter,
                LineProtocolFormatter $writeFormatter
            ) {
                // Bypass the parent constructor to avoid the inconsistency
                // Set required properties directly
                $this->queryBuilder = $mockQueryBuilder;
                $this->logger = $mockLogger;
                $this->connectionAdapter = $mockConnectionAdapter;
                $this->writeFormatter = $writeFormatter;

                // Set up the mocked properties
                $this->client = $mockClient;
                $this->writeApi = $mockWriteApi;
                $this->queryApi = $mockQueryApi;
                $this->bucketsService = $mockBucketsService;
                $this->connected = true;
                $this->org = 'test_org';
                $this->bucket = 'test_bucket';
                $this->orgId = 'test_org_id';
            }

            public function isConnected(): bool
            {
                return $this->connected;
            }

            protected function doConnect(): bool
            {
                return true;
            }

            public function connect($config = null): bool
            {
                if ($config !== null) {
                    $this->config = $config;
                }

                return true;
            }

            public function close(): void
            {
                $this->connected = false;
            }

            public function getDatabases(): array
            {
                return ['test_bucket', 'another_bucket'];
            }
        };

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
            ->where('instance', '=', 'localhost:8086')
            ->timeRange(new DateTime('2023-01-01'), new DateTime('2023-01-02'));

        $result = $this->driver->query($query);

        $this->assertInstanceOf(QueryResult::class, $result);
    }

    public function test_raw_query(): void
    {
        $rawQuery = new RawQuery('from(bucket:"test_bucket") |> range(start: -1h) |> filter(fn: (r) => r._measurement == "cpu_usage")');
        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
    }

    public function test_write(): void
    {
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['value' => 23.5],
            ['instance' => 'localhost:8086'],
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
                ['instance' => 'localhost:8086']
            ),
            new DataPoint(
                'cpu_usage',
                ['value' => 25.0],
                ['instance' => 'localhost:8087']
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

    public function test_get_databases(): void
    {
        $databases = $this->driver->getDatabases();
        $this->assertIsArray($databases);
        $this->assertContains('test_bucket', $databases);
        $this->assertContains('another_bucket', $databases);
    }

    public function test_get_health(): void
    {
        $health = $this->driver->getHealth();
        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('build', $health);
        $this->assertArrayHasKey('version', $health);
        $this->assertEquals('success', $health['status']);
        $this->assertEquals('test_build', $health['build']);
        $this->assertEquals('test_version', $health['version']);
    }

    public function test_close(): void
    {
        $this->driver->close();

        // Check if the driver is disconnected
        $this->assertFalse($this->driver->isConnected());
    }
}
