<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Laravel;

use Mockery;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\Null\NullDriver;
use TimeSeriesPhp\TSDB;

class ConfigurationTest extends LaravelTestCase
{
    public function test_default_driver_is_loaded_from_config(): void
    {
        // Mock the InfluxDB driver
        $driver = Mockery::mock(InfluxDBDriver::class);
        $driver->shouldReceive('getName')->andReturn('influxdb');
        $driver->shouldReceive('connect')->andReturn(true);

        // Set up the driver factory to return our mock driver
        $this->driverFactory->shouldReceive('create')
            ->with('influxdb', Mockery::any())
            ->andReturn($driver);

        // Create a TSDB instance
        $tsdb = new TSDB('influxdb', null, true, $this->container);

        // Verify the driver is loaded
        $this->assertSame($driver, $tsdb->getDriver());
    }

    public function test_custom_default_driver_is_loaded_from_config(): void
    {
        // Mock the Null driver
        $driver = Mockery::mock(NullDriver::class);
        $driver->shouldReceive('getName')->andReturn('null');
        $driver->shouldReceive('connect')->andReturn(true);

        // Set up the driver factory to return our mock driver
        $this->driverFactory->shouldReceive('create')
            ->with('null', Mockery::any())
            ->andReturn($driver);

        // Create a TSDB instance with the null driver
        $tsdb = new TSDB('null', null, true, $this->container);

        // Verify the driver is loaded
        $this->assertSame($driver, $tsdb->getDriver());
    }

    public function test_driver_configuration_is_passed_to_driver_factory(): void
    {
        // Define custom configuration
        $customConfig = [
            'url' => 'http://custom-influxdb:8086',
            'token' => 'custom-token',
            'org' => 'custom-org',
            'bucket' => 'custom-bucket',
        ];

        // Mock the InfluxDB driver
        $driver = Mockery::mock(InfluxDBDriver::class);
        $driver->shouldReceive('getName')->andReturn('influxdb');
        $driver->shouldReceive('connect')->andReturn(true);

        // Set up the driver factory to return our mock driver and capture the config
        $this->driverFactory->shouldReceive('create')
            ->with('influxdb', $customConfig)
            ->andReturn($driver);

        // Create a TSDB instance with custom config
        $tsdb = new TSDB('influxdb', $customConfig, true, $this->container);

        // Verify the driver is loaded
        $this->assertSame($driver, $tsdb->getDriver());
    }

    public function test_logging_configuration_is_available(): void
    {
        // Verify the logging configuration is available
        $config = $this->container->get('config');
        $this->assertTrue($config->get('timeseries.logging.enabled'));
        $this->assertEquals('debug', $config->get('timeseries.logging.level'));

        // Update the logging configuration
        $config->set('timeseries.logging.enabled', false);
        $config->set('timeseries.logging.level', 'error');

        // Verify the updated configuration
        $this->assertFalse($config->get('timeseries.logging.enabled'));
        $this->assertEquals('error', $config->get('timeseries.logging.level'));
    }
}
