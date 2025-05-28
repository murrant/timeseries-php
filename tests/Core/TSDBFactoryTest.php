<?php

namespace TimeSeriesPhp\Tests\Core;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Config\ConfigInterface;
use TimeSeriesPhp\Core\TimeSeriesInterface;
use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Exceptions\DriverException;

class TSDBFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset the registered drivers after each test
        $reflectionClass = new \ReflectionClass(TSDBFactory::class);
        $driversProperty = $reflectionClass->getProperty('drivers');
        $driversProperty->setAccessible(true);
        $driversProperty->setValue(null, []);
    }

    public function test_register_driver(): void
    {
        // Create a mock driver class
        $mockDriverClass = get_class($this->createMock(TimeSeriesInterface::class));

        // Register the driver
        TSDBFactory::registerDriver('mock', $mockDriverClass);

        // Check if the driver is available
        $this->assertContains('mock', TSDBFactory::getAvailableDrivers());
    }

    public function test_get_available_drivers(): void
    {
        // Initially, no drivers should be registered
        $this->assertEmpty(TSDBFactory::getAvailableDrivers());

        // Register some drivers
        $mockDriverClass = get_class($this->createMock(TimeSeriesInterface::class));
        TSDBFactory::registerDriver('mock1', $mockDriverClass);
        TSDBFactory::registerDriver('mock2', $mockDriverClass);

        // Check available drivers
        $this->assertEquals(['mock1', 'mock2'], TSDBFactory::getAvailableDrivers());
    }

    public function test_create_with_valid_driver(): void
    {
        // Create a mock config
        $mockConfig = $this->createMock(ConfigInterface::class);

        // Create a mock driver class
        $mockDriverClass = 'TimeSeriesPhp\Tests\Core\MockDriver';

        // Create the mock driver class if it doesn't exist
        if (! class_exists($mockDriverClass)) {
            eval('
                namespace TimeSeriesPhp\Tests\Core;

                use TimeSeriesPhp\Core\TimeSeriesInterface;
                use TimeSeriesPhp\Config\ConfigInterface;

                class MockDriver implements TimeSeriesInterface {
                    public static $connectCalled = false;

                    public function connect(ConfigInterface $config): bool {
                        self::$connectCalled = true;
                        return true;
                    }

                    public function isConnected(): bool {
                        return true;
                    }

                    public function query(\TimeSeriesPhp\Core\Query $query): \TimeSeriesPhp\Core\QueryResult {
                        return new \TimeSeriesPhp\Core\QueryResult([]);
                    }

                    public function rawQuery(\TimeSeriesPhp\Core\RawQueryInterface $query): \TimeSeriesPhp\Core\QueryResult {
                        return new \TimeSeriesPhp\Core\QueryResult([]);
                    }

                    public function write(\TimeSeriesPhp\Core\DataPoint $dataPoint): bool {
                        return true;
                    }

                    public function writeBatch(array $dataPoints): bool {
                        return true;
                    }

                    public function createDatabase(string $database): bool {
                        return true;
                    }

                    public function deleteDatabase(string $database): bool {
                        return true;
                    }

                    public function getDatabases(): array {
                        return [];
                    }

                    public function deleteMeasurement(string $measurement, ?\DateTime $start = null, ?\DateTime $stop = null): bool {
                        return true;
                    }

                    public function close(): void {
                    }
                }
            ');
        }

        // Reset the static flag
        $mockDriverClass::$connectCalled = false;

        // Register the driver
        // @phpstan-ignore-next-line
        TSDBFactory::registerDriver('mock', $mockDriverClass);

        // Create an instance using the factory
        $instance = TSDBFactory::create('mock', $mockConfig);

        // Verify the instance is our mock class
        $this->assertTrue($instance instanceof $mockDriverClass);

        // Verify connect was called
        $this->assertTrue($mockDriverClass::$connectCalled, 'connect() method was not called');
    }

    public function test_create_with_invalid_driver(): void
    {
        $mockConfig = $this->createMock(ConfigInterface::class);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage("Driver 'invalid' not registered");

        TSDBFactory::create('invalid', $mockConfig);
    }

    public function test_create_with_invalid_driver_class(): void
    {
        // Create a mock that doesn't implement TimeSeriesInterface
        $mockClass = get_class($this->createMock(\stdClass::class));
        $mockConfig = $this->createMock(ConfigInterface::class);

        $this->expectException(DriverException::class);
        // We only check for a partial message since the class name is dynamic
        $this->expectExceptionMessageMatches('/must implement TimeSeriesInterface/');

        // Register the invalid driver - this should throw the exception
        TSDBFactory::registerDriver('invalid', $mockClass);

        // This line should not be reached
        TSDBFactory::create('invalid', $mockConfig);
    }
}
