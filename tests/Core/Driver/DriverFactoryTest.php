<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Core\Driver;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Attributes\Driver;
use TimeSeriesPhp\Core\DependencyInjection\DriverCompilerPass;
use TimeSeriesPhp\Core\Driver\DriverFactory;
use TimeSeriesPhp\Drivers\Example\ExampleDriver;
use TimeSeriesPhp\Drivers\Example\ExampleDriverConfiguration;
use TimeSeriesPhp\Exceptions\Driver\DriverNotFoundException;

class DriverFactoryTest extends TestCase
{
    private ContainerBuilder $container;

    private DriverFactory $factory;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder;

        // Register the example driver
        $this->container->register(ExampleDriver::class, ExampleDriver::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);

        // Register the example driver configuration
        $this->container->register(ExampleDriverConfiguration::class, ExampleDriverConfiguration::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);

        // Register a logger
        $this->container->register(LoggerInterface::class, NullLogger::class)
            ->setAutoconfigured(true)
            ->setAutowired(true);

        // Add the driver compiler pass
        $this->container->addCompilerPass(new DriverCompilerPass);

        // Compile the container
        $this->container->compile();

        // Create the factory
        $this->factory = new DriverFactory($this->container);
    }

    public function test_create_driver_by_name(): void
    {
        // Create a driver by name
        $driver = $this->factory->create('example', ['database' => 'test_db']);

        // Assert that the driver is an instance of TimeSeriesInterface
        $this->assertInstanceOf(TimeSeriesInterface::class, $driver);

        // Assert that the driver is an instance of ExampleDriver
        $this->assertInstanceOf(ExampleDriver::class, $driver);

        // Assert that the driver is an instance of ConfigurableInterface
        $this->assertInstanceOf(ConfigurableInterface::class, $driver);
    }

    public function test_create_driver_with_configuration(): void
    {
        // Create a driver with configuration
        $driver = $this->factory->create('example', [
            'database' => 'test_db',
            'host' => 'example.com',
            'port' => 8086,
            'username' => 'user',
            'password' => 'pass',
            'use_ssl' => true,
            'timeout' => 60,
            'mode' => 'advanced',
        ]);

        // Assert that the driver is an instance of ExampleDriver
        $this->assertInstanceOf(ExampleDriver::class, $driver);
    }

    public function test_create_driver_with_invalid_name(): void
    {
        // Expect an exception when creating a driver with an invalid name
        $this->expectException(DriverNotFoundException::class);
        $this->expectExceptionMessage('Driver "invalid" not found');

        $this->factory->create('invalid');
    }
}
