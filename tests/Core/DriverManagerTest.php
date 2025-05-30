<?php

namespace TimeSeriesPhp\Tests\Core;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Contracts\Config\ConfigInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Factory\DriverManager;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

class DriverManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset the factory instance after each test
        DriverManager::reset();
    }

    public function test_register_driver(): void
    {
        // Create a mock driver class
        $mockDriverClass = get_class($this->createMock(TimeSeriesInterface::class));

        // Create a mock config class
        $mockConfigClass = get_class($this->createMock(ConfigInterface::class));

        // Register the driver with explicit config class
        DriverManager::register('mock', $mockDriverClass, $mockConfigClass);

        // Check if the driver is available
        $this->assertContains('mock', DriverManager::getAvailableDrivers());

        // Check if the config class is registered
        $this->assertEquals($mockConfigClass, DriverManager::getConfigClass('mock'));
    }

    public function test_register_driver_with_inferred_config(): void
    {
        // Use the test driver class from the data directory
        $mockDriverClass = 'TimeSeriesPhp\Tests\Core\data\TestDriver';
        $mockConfigClass = 'TimeSeriesPhp\Tests\Core\data\TestConfig';

        // Register the driver without specifying the config class
        /** @var class-string<\TimeSeriesPhp\Tests\Core\data\TestDriver> $mockDriverClass */
        DriverManager::register('test', $mockDriverClass);

        // Check if the driver is available
        $this->assertContains('test', DriverManager::getAvailableDrivers());

        // Check if the config class was correctly inferred
        $this->assertEquals($mockConfigClass, DriverManager::getConfigClass('test'));
    }

    public function test_get_available_drivers(): void
    {
        // Initially, no drivers should be registered
        $this->assertEmpty(DriverManager::getAvailableDrivers());

        // Register some drivers
        $mockDriverClass = get_class($this->createMock(TimeSeriesInterface::class));
        $mockConfigClass = get_class($this->createMock(ConfigInterface::class));
        DriverManager::register('mock1', $mockDriverClass, $mockConfigClass);
        DriverManager::register('mock2', $mockDriverClass, $mockConfigClass);

        // Check available drivers
        $this->assertEquals(['mock1', 'mock2'], DriverManager::getAvailableDrivers());
    }

    public function test_create_with_valid_driver_and_explicit_config(): void
    {
        // Create a mock config
        /** @var ConfigInterface&\PHPUnit\Framework\MockObject\MockObject */
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
        DriverManager::register('mock', $mockDriverClass, $mockConfigClass);

        // Create an instance using the factory with explicit config
        $instance = DriverManager::create('mock', $mockConfig);

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
        DriverManager::register('mock', $mockDriverClass, $mockConfigClass);

        // Create an instance using the factory without providing a config
        $instance = DriverManager::create('mock');

        // Verify the instance is our mock class
        $this->assertTrue($instance instanceof $mockDriverClass);

        // Verify connect was called
        $this->assertTrue($mockDriverClass::$connectCalled, 'connect() method was not called');

        // Verify a default config was created and passed
        $this->assertInstanceOf($mockConfigClass, $mockDriverClass::$lastConfig, 'Default config was not created');
    }

    public function test_create_with_invalid_driver(): void
    {
        /** @var ConfigInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockConfig = $this->createMock(ConfigInterface::class);

        $this->expectException(DriverException::class);
        $this->expectExceptionMessage("Driver 'invalid' not registered");

        DriverManager::create('invalid', $mockConfig);
    }

    public function test_create_with_invalid_driver_class(): void
    {
        // Create a mock that doesn't implement TimeSeriesInterface
        $mockClass = get_class($this->createMock(\stdClass::class));
        /** @var ConfigInterface&\PHPUnit\Framework\MockObject\MockObject */
        $mockConfig = $this->createMock(ConfigInterface::class);

        $this->expectException(DriverException::class);
        // We only check for a partial message since the class name is dynamic
        $this->expectExceptionMessageMatches('/must implement TimeSeriesInterface/');

        // Register the invalid driver - this should throw the exception
        DriverManager::register('invalid', $mockClass, get_class($mockConfig));

        // This line should not be reached
        DriverManager::create('invalid', $mockConfig);
    }

    public function test_create_config(): void
    {
        // Create a mock config class
        $mockConfigClass = get_class($this->createMock(ConfigInterface::class));
        $mockDriverClass = get_class($this->createMock(TimeSeriesInterface::class));

        // Register the driver
        DriverManager::register('mock', $mockDriverClass, $mockConfigClass);

        // Create a config instance
        $config = DriverManager::createConfig('mock', ['option' => 'value']);

        // Check that the config is an instance of the expected class
        $this->assertInstanceOf($mockConfigClass, $config);
    }

    public function test_create_config_with_invalid_driver(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessage('No configuration class registered for driver: invalid');

        // Try to create a config for an unregistered driver
        DriverManager::createConfig('invalid');
    }

    // The inferConfigClassName method is now private in TSDBFactory
    // and is tested indirectly through test_register_driver_with_inferred_config
}
