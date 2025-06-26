<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Drivers\Null;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Contracts\Query\QueryBuilderInterface;
use TimeSeriesPhp\Contracts\Query\RawQueryInterface;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Drivers\Null\NullDriver;

class NullDriverTest extends TestCase
{
    private NullDriver $driver;

    private QueryBuilderInterface $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = $this->createMock(QueryBuilderInterface::class);
        $this->driver = new NullDriver($this->queryBuilder, new NullLogger, new \TimeSeriesPhp\Drivers\Null\NullConfig);
    }

    public function test_implements_interfaces(): void
    {
        // Assert that the driver implements TimeSeriesInterface
        $this->assertInstanceOf(TimeSeriesInterface::class, $this->driver);

        // Assert that the driver implements ConfigurableInterface
        $this->assertInstanceOf(ConfigurableInterface::class, $this->driver);
    }

    public function test_configure(): void
    {
        // Configure the driver
        $this->driver->configure([
            'debug' => true,
        ]);

        // No assertion needed, just testing that it doesn't throw an exception
        $this->assertTrue(true);
    }

    public function test_connect(): void
    {
        // Connect to the database
        $result = $this->driver->connect();

        // Assert that the connection was successful
        $this->assertTrue($result);

        // Assert that the driver is connected
        $this->assertTrue($this->driver->isConnected());
    }

    public function test_write(): void
    {
        // Connect to the database
        $this->driver->connect();

        // Create a data point
        $dataPoint = new DataPoint('test_measurement', ['value' => 42.0], ['tag' => 'test']);

        // Write the data point
        $result = $this->driver->write($dataPoint);

        // Assert that the write was successful
        $this->assertTrue($result);
    }

    public function test_write_batch(): void
    {
        // Connect to the database
        $this->driver->connect();

        // Create data points
        $dataPoints = [
            new DataPoint('test_measurement', ['value' => 42.0], ['tag' => 'test1']),
            new DataPoint('test_measurement', ['value' => 43.0], ['tag' => 'test2']),
        ];

        // Write the data points
        $result = $this->driver->writeBatch($dataPoints);

        // Assert that the write was successful
        $this->assertTrue($result);
    }

    public function test_raw_query(): void
    {
        // Connect to the database
        $this->driver->connect();

        // Create a mock raw query
        $rawQuery = $this->createMock(RawQueryInterface::class);

        // Execute the raw query
        $result = $this->driver->rawQuery($rawQuery);

        // Assert that the result is a QueryResult
        $this->assertInstanceOf(QueryResult::class, $result);

        // Assert that the result is empty
        $this->assertEquals(0, $result->count());
    }

    public function test_create_database(): void
    {
        // Connect to the database
        $this->driver->connect();

        // Create a database
        $result = $this->driver->createDatabase('test_db');

        // Assert that the operation was successful
        $this->assertTrue($result);
    }

    public function test_get_databases(): void
    {
        // Connect to the database
        $this->driver->connect();

        // Get databases
        $result = $this->driver->getDatabases();

        // Assert that the result is an array
        $this->assertIsArray($result);

        // Assert that the result is empty
        $this->assertEmpty($result);
    }

    public function test_delete_database(): void
    {
        // Connect to the database
        $this->driver->connect();

        // Delete a database
        $result = $this->driver->deleteDatabase('test_db');

        // Assert that the operation was successful
        $this->assertTrue($result);
    }

    public function test_delete_measurement(): void
    {
        // Connect to the database
        $this->driver->connect();

        // Delete a measurement
        $result = $this->driver->deleteMeasurement('test_measurement');

        // Assert that the operation was successful
        $this->assertTrue($result);
    }

    public function test_close(): void
    {
        // Connect to the database
        $this->driver->connect();

        // Assert that the driver is connected
        $this->assertTrue($this->driver->isConnected());

        // Close the connection
        $this->driver->close();

        // Assert that the driver is no longer connected
        $this->assertFalse($this->driver->isConnected());
    }
}
