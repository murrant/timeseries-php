<?php

namespace TimeSeriesPhp\Tests\Drivers\Aggregate;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Drivers\Aggregate\AggregateDriver;
use TimeSeriesPhp\Drivers\Aggregate\Config\AggregateConfig;

class AggregateDriverTest extends TestCase
{
    private AggregateDriver $driver;
    private TimeSeriesInterface $mockWriteDb1;
    private TimeSeriesInterface $mockWriteDb2;
    private TimeSeriesInterface $mockReadDb;
    private AggregateConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock write databases
        $this->mockWriteDb1 = $this->createMock(TimeSeriesInterface::class);
        $this->mockWriteDb2 = $this->createMock(TimeSeriesInterface::class);
        $this->mockReadDb = $this->createMock(TimeSeriesInterface::class);

        // Create a test subclass of AggregateDriver that doesn't actually connect to real databases
        $this->driver = new class($this->mockWriteDb1, $this->mockWriteDb2, $this->mockReadDb) extends AggregateDriver {
            private TimeSeriesInterface $mockWriteDb1;
            private TimeSeriesInterface $mockWriteDb2;
            private TimeSeriesInterface $mockReadDb;

            public function __construct(
                TimeSeriesInterface $mockWriteDb1,
                TimeSeriesInterface $mockWriteDb2,
                TimeSeriesInterface $mockReadDb
            ) {
                $this->mockWriteDb1 = $mockWriteDb1;
                $this->mockWriteDb2 = $mockWriteDb2;
                $this->mockReadDb = $mockReadDb;
            }

            protected function doConnect(): bool
            {
                $this->writeDatabases = [$this->mockWriteDb1, $this->mockWriteDb2];
                $this->readDatabase = $this->mockReadDb;
                $this->connected = true;
                return true;
            }
        };

        // Create a config with two write databases and one read database
        $this->config = new AggregateConfig([
            'write_databases' => [
                [
                    'driver' => 'influxdb',
                    'url' => 'http://influxdb1.example.com:8086',
                ],
                [
                    'driver' => 'influxdb',
                    'url' => 'http://influxdb2.example.com:8086',
                ],
            ],
            'read_database' => [
                'driver' => 'influxdb',
                'url' => 'http://influxdb-read.example.com:8086',
            ],
        ]);

        // Connect the driver
        $this->driver->connect($this->config);
    }

    public function test_connect(): void
    {
        $this->assertTrue($this->driver->isConnected());
    }

    public function test_write(): void
    {
        $dataPoint = new DataPoint('test_measurement', ['value' => 42]);

        // Set up expectations for write databases
        $this->mockWriteDb1->expects($this->once())
            ->method('write')
            ->with($dataPoint)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('write')
            ->with($dataPoint)
            ->willReturn(true);

        // Read database should not be written to
        $this->mockReadDb->expects($this->never())
            ->method('write');

        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);
    }

    public function test_write_partial_failure(): void
    {
        $dataPoint = new DataPoint('test_measurement', ['value' => 42]);

        // First write succeeds, second fails
        $this->mockWriteDb1->expects($this->once())
            ->method('write')
            ->with($dataPoint)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('write')
            ->with($dataPoint)
            ->willReturn(false);

        $result = $this->driver->write($dataPoint);
        $this->assertFalse($result);
    }

    public function test_write_batch(): void
    {
        $dataPoints = [
            new DataPoint('test_measurement1', ['value' => 42]),
            new DataPoint('test_measurement2', ['value' => 43]),
        ];

        // Set up expectations for write databases
        $this->mockWriteDb1->expects($this->once())
            ->method('writeBatch')
            ->with($dataPoints)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('writeBatch')
            ->with($dataPoints)
            ->willReturn(true);

        // Read database should not be written to
        $this->mockReadDb->expects($this->never())
            ->method('writeBatch');

        $result = $this->driver->writeBatch($dataPoints);
        $this->assertTrue($result);
    }

    public function test_write_batch_partial_failure(): void
    {
        $dataPoints = [
            new DataPoint('test_measurement1', ['value' => 42]),
            new DataPoint('test_measurement2', ['value' => 43]),
        ];

        // First write succeeds, second fails
        $this->mockWriteDb1->expects($this->once())
            ->method('writeBatch')
            ->with($dataPoints)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('writeBatch')
            ->with($dataPoints)
            ->willReturn(false);

        $result = $this->driver->writeBatch($dataPoints);
        $this->assertFalse($result);
    }

    public function test_raw_query(): void
    {
        $rawQuery = $this->createMock(RawQueryInterface::class);
        $queryResult = new QueryResult([]);

        // Set up expectations for read database
        $this->mockReadDb->expects($this->once())
            ->method('rawQuery')
            ->with($rawQuery)
            ->willReturn($queryResult);

        // Write databases should not be queried
        $this->mockWriteDb1->expects($this->never())
            ->method('rawQuery');
        $this->mockWriteDb2->expects($this->never())
            ->method('rawQuery');

        $result = $this->driver->rawQuery($rawQuery);
        $this->assertSame($queryResult, $result);
    }

    public function test_create_database(): void
    {
        $database = 'test_database';

        // Set up expectations for write databases
        $this->mockWriteDb1->expects($this->once())
            ->method('createDatabase')
            ->with($database)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('createDatabase')
            ->with($database)
            ->willReturn(true);

        // Read database should not be used
        $this->mockReadDb->expects($this->never())
            ->method('createDatabase');

        $result = $this->driver->createDatabase($database);
        $this->assertTrue($result);
    }

    public function test_create_database_partial_failure(): void
    {
        $database = 'test_database';

        // First create succeeds, second fails
        $this->mockWriteDb1->expects($this->once())
            ->method('createDatabase')
            ->with($database)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('createDatabase')
            ->with($database)
            ->willReturn(false);

        $result = $this->driver->createDatabase($database);
        $this->assertFalse($result);
    }

    public function test_get_databases(): void
    {
        $databases = ['db1', 'db2'];

        // Set up expectations for read database
        $this->mockReadDb->expects($this->once())
            ->method('getDatabases')
            ->willReturn($databases);

        // Write databases should not be used
        $this->mockWriteDb1->expects($this->never())
            ->method('getDatabases');
        $this->mockWriteDb2->expects($this->never())
            ->method('getDatabases');

        $result = $this->driver->getDatabases();
        $this->assertSame($databases, $result);
    }

    public function test_delete_database(): void
    {
        $database = 'test_database';

        // Set up expectations for write databases
        $this->mockWriteDb1->expects($this->once())
            ->method('deleteDatabase')
            ->with($database)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('deleteDatabase')
            ->with($database)
            ->willReturn(true);

        // Read database should not be used
        $this->mockReadDb->expects($this->never())
            ->method('deleteDatabase');

        $result = $this->driver->deleteDatabase($database);
        $this->assertTrue($result);
    }

    public function test_delete_database_partial_failure(): void
    {
        $database = 'test_database';

        // First delete succeeds, second fails
        $this->mockWriteDb1->expects($this->once())
            ->method('deleteDatabase')
            ->with($database)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('deleteDatabase')
            ->with($database)
            ->willReturn(false);

        $result = $this->driver->deleteDatabase($database);
        $this->assertFalse($result);
    }

    public function test_delete_measurement(): void
    {
        $measurement = 'test_measurement';
        $start = new DateTime('2023-01-01');
        $stop = new DateTime('2023-01-02');

        // Set up expectations for write databases
        $this->mockWriteDb1->expects($this->once())
            ->method('deleteMeasurement')
            ->with($measurement, $start, $stop)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('deleteMeasurement')
            ->with($measurement, $start, $stop)
            ->willReturn(true);

        // Read database should not be used
        $this->mockReadDb->expects($this->never())
            ->method('deleteMeasurement');

        $result = $this->driver->deleteMeasurement($measurement, $start, $stop);
        $this->assertTrue($result);
    }

    public function test_delete_measurement_partial_failure(): void
    {
        $measurement = 'test_measurement';

        // First delete succeeds, second fails
        $this->mockWriteDb1->expects($this->once())
            ->method('deleteMeasurement')
            ->with($measurement, null, null)
            ->willReturn(true);

        $this->mockWriteDb2->expects($this->once())
            ->method('deleteMeasurement')
            ->with($measurement, null, null)
            ->willReturn(false);

        $result = $this->driver->deleteMeasurement($measurement);
        $this->assertFalse($result);
    }

    public function test_close(): void
    {
        // Set up expectations for all databases
        $this->mockWriteDb1->expects($this->once())
            ->method('close');

        $this->mockWriteDb2->expects($this->once())
            ->method('close');

        $this->mockReadDb->expects($this->once())
            ->method('close');

        $this->driver->close();
        $this->assertFalse($this->driver->isConnected());
    }

    public function test_read_database_fallback(): void
    {
        // Create a driver with write databases only
        $driver = new class($this->mockWriteDb1, $this->mockWriteDb2) extends AggregateDriver {
            private TimeSeriesInterface $mockWriteDb1;
            private TimeSeriesInterface $mockWriteDb2;

            public function __construct(
                TimeSeriesInterface $mockWriteDb1,
                TimeSeriesInterface $mockWriteDb2
            ) {
                $this->mockWriteDb1 = $mockWriteDb1;
                $this->mockWriteDb2 = $mockWriteDb2;
            }

            protected function doConnect(): bool
            {
                $this->writeDatabases = [$this->mockWriteDb1, $this->mockWriteDb2];
                $this->readDatabase = $this->mockWriteDb1; // Use first write database for reading
                $this->connected = true;
                return true;
            }
        };

        // Create a config with write databases only
        $config = new AggregateConfig([
            'write_databases' => [
                [
                    'driver' => 'influxdb',
                    'url' => 'http://influxdb1.example.com:8086',
                ],
                [
                    'driver' => 'influxdb',
                    'url' => 'http://influxdb2.example.com:8086',
                ],
            ],
            // No read_database specified
        ]);

        // Connect the driver
        $driver->connect($config);

        $rawQuery = $this->createMock(RawQueryInterface::class);
        $queryResult = new QueryResult([]);

        // Set up expectations for first write database (used as read database)
        $this->mockWriteDb1->expects($this->once())
            ->method('rawQuery')
            ->with($rawQuery)
            ->willReturn($queryResult);

        // Second write database should not be queried
        $this->mockWriteDb2->expects($this->never())
            ->method('rawQuery');

        $result = $driver->rawQuery($rawQuery);
        $this->assertSame($queryResult, $result);
    }
}
