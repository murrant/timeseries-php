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

        // Reset the config classes as well
        $configClassesProperty = $reflectionClass->getProperty('configClasses');
        $configClassesProperty->setAccessible(true);
        $configClassesProperty->setValue(null, []);
    }

    public function test_register_driver(): void
    {
        // Create a mock driver class
        $mockDriverClass = get_class($this->createMock(TimeSeriesInterface::class));

        // Create a mock config class
        $mockConfigClass = get_class($this->createMock(ConfigInterface::class));

        // Register the driver with explicit config class
        TSDBFactory::registerDriver('mock', $mockDriverClass, $mockConfigClass);

        // Check if the driver is available
        $this->assertContains('mock', TSDBFactory::getAvailableDrivers());

        // Check if the config class is registered
        $this->assertEquals($mockConfigClass, TSDBFactory::getConfigClass('mock'));
    }

    public function test_register_driver_with_inferred_config(): void
    {
        // Use the test driver class from the data directory
        $mockDriverClass = 'TimeSeriesPhp\Tests\Core\data\TestDriver';
        $mockConfigClass = 'TimeSeriesPhp\Tests\Core\data\TestConfig';

        // Register the driver without specifying the config class
        /** @var class-string<\TimeSeriesPhp\Tests\Core\data\TestDriver> $mockDriverClass */
        TSDBFactory::registerDriver('test', $mockDriverClass);

        // Check if the driver is available
        $this->assertContains('test', TSDBFactory::getAvailableDrivers());

        // Check if the config class was correctly inferred
        $this->assertEquals($mockConfigClass, TSDBFactory::getConfigClass('test'));
    }

    public function test_get_available_drivers(): void
    {
        // Initially, no drivers should be registered
        $this->assertEmpty(TSDBFactory::getAvailableDrivers());

        // Register some drivers
        $mockDriverClass = get_class($this->createMock(TimeSeriesInterface::class));
        $mockConfigClass = get_class($this->createMock(ConfigInterface::class));
        TSDBFactory::registerDriver('mock1', $mockDriverClass, $mockConfigClass);
        TSDBFactory::registerDriver('mock2', $mockDriverClass, $mockConfigClass);

        // Check available drivers
        $this->assertEquals(['mock1', 'mock2'], TSDBFactory::getAvailableDrivers());
    }

    public function test_create_with_valid_driver_and_explicit_config(): void
    {
        // Create a mock config
        $mockConfig = $this->createMock(ConfigInterface::class);

        // Use the mock driver class from the data directory
        $mockDriverClass = 'TimeSeriesPhp\Tests\Core\data\MockDriver';
        $mockConfigClass = 'TimeSeriesPhp\Tests\Core\data\MockConfig';

        // Reset the static flags
        $mockDriverClass::$connectCalled = false;
        $mockDriverClass::$lastConfig = null;

        // Register the driver
        /** @var class-string<\TimeSeriesPhp\Tests\Core\data\MockDriver> $mockDriverClass */
        /** @var class-string<\TimeSeriesPhp\Tests\Core\data\MockConfig> $mockConfigClass */
        TSDBFactory::registerDriver('mock', $mockDriverClass, $mockConfigClass);

        // Create an instance using the factory with explicit config
        $instance = TSDBFactory::create('mock', $mockConfig);

        // Verify the instance is our mock class
        $this->assertTrue($instance instanceof $mockDriverClass);

        // Verify connect was called
        $this->assertTrue($mockDriverClass::$connectCalled, 'connect() method was not called');

        // Verify the correct config was passed
        $this->assertSame($mockConfig, $mockDriverClass::$lastConfig, 'Explicit config was not passed to connect()');
    }

    public function test_create_with_valid_driver_and_default_config(): void
    {
        // Use the mock driver class from the data directory
        /** @var class-string<\TimeSeriesPhp\Tests\Core\data\MockDriver> $mockDriverClass */
        $mockDriverClass = 'TimeSeriesPhp\Tests\Core\data\MockDriver';

        // Use the mock config class from the data directory
        /** @var class-string<\TimeSeriesPhp\Tests\Core\data\MockConfig> $mockConfigClass */
        $mockConfigClass = 'TimeSeriesPhp\Tests\Core\data\MockConfig';

        // Reset the static flags
        $mockDriverClass::$connectCalled = false;
        $mockDriverClass::$lastConfig = null;

        // Register the driver
        TSDBFactory::registerDriver('mock', $mockDriverClass, $mockConfigClass);

        // Create an instance using the factory without providing a config
        $instance = TSDBFactory::create('mock');

        // Verify the instance is our mock class
        $this->assertTrue($instance instanceof $mockDriverClass);

        // Verify connect was called
        $this->assertTrue($mockDriverClass::$connectCalled, 'connect() method was not called');

        // Verify a default config was created and passed
        $this->assertInstanceOf($mockConfigClass, $mockDriverClass::$lastConfig, 'Default config was not created');
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
        TSDBFactory::registerDriver('invalid', $mockClass, get_class($mockConfig));

        // This line should not be reached
        TSDBFactory::create('invalid', $mockConfig);
    }

    public function test_create_config(): void
    {
        // Create a mock config class
        $mockConfigClass = get_class($this->createMock(ConfigInterface::class));
        $mockDriverClass = get_class($this->createMock(TimeSeriesInterface::class));

        // Register the driver
        TSDBFactory::registerDriver('mock', $mockDriverClass, $mockConfigClass);

        // Create a config instance
        $config = TSDBFactory::createConfig('mock', ['option' => 'value']);

        // Check that the config is an instance of the expected class
        $this->assertInstanceOf($mockConfigClass, $config);
    }

    public function test_create_config_with_invalid_driver(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('No configuration class registered for driver: invalid');

        // Try to create a config for an unregistered driver
        TSDBFactory::createConfig('invalid');
    }

    public function test_infer_config_class_name(): void
    {
        // Use reflection to access the private method
        $reflectionClass = new \ReflectionClass(TSDBFactory::class);
        $method = $reflectionClass->getMethod('inferConfigClassName');
        $method->setAccessible(true);

        // Test with various driver class names
        $this->assertEquals(
            'TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig',
            $method->invoke(null, 'TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver')
        );

        $this->assertEquals(
            'TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig',
            $method->invoke(null, 'TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver')
        );

        $this->assertEquals(
            'App\CustomConfig',
            $method->invoke(null, 'App\CustomDriver')
        );
    }
}
