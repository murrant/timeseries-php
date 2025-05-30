<?php

namespace TimeSeriesPhp\Tests\Core;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Factory\TSDBFactory;
use TimeSeriesPhp\Exceptions\Driver\DriverException;
use TimeSeriesPhp\Tests\Core\data\TestTSDBFactory;

class TSDBFactoryInstanceTest extends TestCase
{
    private TSDBFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new TSDBFactory;
    }

    public function test_register_and_create_driver(): void
    {
        // Create a mock driver and config
        /** @var TimeSeriesInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockDriver = $this->createMock(TimeSeriesInterface::class);
        /** @var ConfigInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockConfig = $this->createMock(ConfigInterface::class);

        // Get the class names
        $mockDriverClass = get_class($mockDriver);
        $mockConfigClass = get_class($mockConfig);

        // Register the driver
        $this->factory->registerDriver($mockDriverClass, 'mock', $mockConfigClass);

        // Check that the driver is registered
        $this->assertTrue($this->factory->hasDriver('mock'));

        // Create an instance of the driver
        $instance = $this->factory->create('mock', $mockConfig, false);

        // Check that the instance is of the expected class
        $this->assertInstanceOf($mockDriverClass, $instance);
    }

    public function test_register_default_drivers(): void
    {
        // Create a new factory instance
        $factory = new TSDBFactory;

        // Register default drivers
        $factory->registerDefaultDrivers();

        // Check that at least one driver is registered
        $this->assertGreaterThan(0, count($factory->getAvailableDrivers()));
    }

    public function test_unregister_driver(): void
    {
        // Create a mock driver and config
        /** @var TimeSeriesInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockDriver = $this->createMock(TimeSeriesInterface::class);
        /** @var ConfigInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockConfig = $this->createMock(ConfigInterface::class);

        // Get the class names
        $mockDriverClass = get_class($mockDriver);
        $mockConfigClass = get_class($mockConfig);

        // Register the driver
        $this->factory->registerDriver($mockDriverClass, 'mock', $mockConfigClass);

        // Check that the driver is registered
        $this->assertTrue($this->factory->hasDriver('mock'));

        // Unregister the driver
        $result = $this->factory->unregisterDriver('mock');

        // Check that the unregister was successful
        $this->assertTrue($result);

        // Check that the driver is no longer registered
        $this->assertFalse($this->factory->hasDriver('mock'));
    }

    public function test_create_with_invalid_driver(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage("Driver 'invalid' not registered");

        /** @var ConfigInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockConfig = $this->createMock(ConfigInterface::class);

        $this->factory->create('invalid', $mockConfig);
    }

    public function test_create_config(): void
    {
        // Create a mock config class
        /** @var ConfigInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockConfig = $this->createMock(ConfigInterface::class);
        /** @var TimeSeriesInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockDriver = $this->createMock(TimeSeriesInterface::class);
        $mockConfigClass = get_class($mockConfig);
        $mockDriverClass = get_class($mockDriver);

        // Register the driver
        $this->factory->registerDriver($mockDriverClass, 'mock', $mockConfigClass);

        // Create a config instance
        $config = $this->factory->createConfig('mock', ['option' => 'value']);

        // Check that the config is an instance of the expected class
        $this->assertInstanceOf($mockConfigClass, $config);
    }

    public function test_create_config_with_invalid_driver(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('No configuration class registered for driver: invalid');

        // Try to create a config for an unregistered driver
        $this->factory->createConfig('invalid');
    }

    public function test_register_driver_with_inferred_name_and_config(): void
    {
        // Use the test driver class from the data directory
        $mockDriverClass = 'TimeSeriesPhp\Tests\Core\data\TestDriver';
        $mockConfigClass = 'TimeSeriesPhp\Tests\Core\data\TestConfig';

        // Register the driver without specifying the name or config class
        /** @var class-string<\TimeSeriesPhp\Tests\Core\data\TestDriver> $mockDriverClass */
        $this->factory->registerDriver($mockDriverClass);

        // Check if the driver is available with the name from the Driver attribute
        $this->assertTrue($this->factory->hasDriver('test'));

        // Check if the config class was correctly inferred from the Driver attribute
        $this->assertEquals($mockConfigClass, $this->factory->getConfigClass('test'));
    }

    public function test_register_drivers_from_composer(): void
    {
        // Create a test factory
        $factory = new TestTSDBFactory;

        // Use the test driver class from the data directory
        $mockDriverClass = 'TimeSeriesPhp\Tests\Core\data\TestDriver';
        $mockConfigClass = 'TimeSeriesPhp\Tests\Core\data\TestConfig';

        // Set up test data for a package with a driver in its "extra" section
        $factory->setPackageExtra('test/package', [
            'timeseries-php' => [
                'drivers' => [
                    $mockDriverClass,
                ],
            ],
        ]);

        // Register drivers from Composer packages
        $factory->registerDriversFromComposer();

        // Check that the driver is registered with the name from the Driver attribute
        $this->assertTrue($factory->hasDriver('test'));

        // Check that the driver class and config class are correct
        $this->assertEquals($mockDriverClass, $factory->getDriverClass('test'));
        $this->assertEquals($mockConfigClass, $factory->getConfigClass('test'));
    }
}
