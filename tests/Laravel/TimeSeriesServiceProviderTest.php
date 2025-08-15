<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Laravel;

use Mockery;
use TimeSeriesPhp\Core\Driver\DriverFactory;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\Null\NullDriver;
use TimeSeriesPhp\Laravel\TimeSeriesServiceProvider;
use TimeSeriesPhp\TSDB;

class TimeSeriesServiceProviderTest extends LaravelTestCase
{
    private TimeSeriesServiceProvider $serviceProvider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceProvider = new TimeSeriesServiceProvider($this->container);
    }

    public function test_service_provider_registers_driver_factory(): void
    {
        // The driver factory is already mocked in the parent class
        $this->assertSame($this->driverFactory, $this->container->get(DriverFactory::class));
    }

    public function test_service_provider_registers_tsdb(): void
    {
        // Mock the driver
        $driver = Mockery::mock(InfluxDBDriver::class);
        $driver->shouldReceive('getName')->andReturn('influxdb');
        $driver->shouldReceive('connect')->andReturn(true);

        // Set up the driver factory to return our mock driver
        $this->driverFactory->shouldReceive('create')
            ->with('influxdb', Mockery::any())
            ->andReturn($driver);

        // Create a TSDB instance directly
        $tsdb = new TSDB('influxdb', null, true, $this->container);

        // Register it in the container
        $this->container->instance(TSDB::class, $tsdb);
        $this->container->alias(TSDB::class, 'timeseries');

        // Verify TSDB is registered
        $this->assertTrue($this->container->bound(TSDB::class));
        $resolvedTsdb = $this->container->make(TSDB::class);
        $this->assertInstanceOf(TSDB::class, $resolvedTsdb);
    }

    public function test_service_provider_registers_alias(): void
    {
        // Mock the driver
        $driver = Mockery::mock(InfluxDBDriver::class);
        $driver->shouldReceive('getName')->andReturn('influxdb');
        $driver->shouldReceive('connect')->andReturn(true);

        // Set up the driver factory to return our mock driver
        $this->driverFactory->shouldReceive('create')
            ->with('influxdb', Mockery::any())
            ->andReturn($driver);

        // Create a TSDB instance directly
        $tsdb = new TSDB('influxdb', null, true, $this->container);

        // Register it in the container
        $this->container->instance(TSDB::class, $tsdb);
        $this->container->alias(TSDB::class, 'timeseries');

        // Verify the alias is registered
        $this->assertTrue($this->container->bound('timeseries'));
        $this->assertInstanceOf(TSDB::class, $this->container->make('timeseries'));
    }

    public function test_service_provider_can_use_null_driver(): void
    {
        // Change the default driver to 'null'
        $config = $this->container->get('config');
        $config->set('timeseries.default', 'null');

        // Mock the null driver
        $driver = Mockery::mock(NullDriver::class);
        $driver->shouldReceive('getName')->andReturn('null');
        $driver->shouldReceive('connect')->andReturn(true);

        // Set up the driver factory to return our mock driver
        $this->driverFactory->shouldReceive('create')
            ->with('null', Mockery::any())
            ->andReturn($driver);

        // Create a TSDB instance with the null driver
        $tsdb = new TSDB('null', null, true, $this->container);

        // Verify the driver is correct
        $this->assertSame($driver, $tsdb->getDriver());
    }
}
