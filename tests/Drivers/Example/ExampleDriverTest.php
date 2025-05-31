<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Drivers\Example;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Contracts\Driver\ConfigurableInterface;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Drivers\Example\ExampleDriver;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;
use TimeSeriesPhp\Exceptions\Driver\DatabaseException;
use TimeSeriesPhp\Exceptions\Driver\WriteException;

class ExampleDriverTest extends TestCase
{
    private ExampleDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new ExampleDriver();
    }
    
    public function testImplementsInterfaces(): void
    {
        // Assert that the driver implements TimeSeriesInterface
        $this->assertInstanceOf(TimeSeriesInterface::class, $this->driver);
        
        // Assert that the driver implements ConfigurableInterface
        $this->assertInstanceOf(ConfigurableInterface::class, $this->driver);
    }
    
    public function testConfigure(): void
    {
        // Configure the driver
        $this->driver->configure([
            'database' => 'test_db',
            'host' => 'example.com',
            'port' => 8086,
            'username' => 'user',
            'password' => 'pass',
            'use_ssl' => true,
            'timeout' => 60,
            'mode' => 'advanced',
        ]);
        
        // No assertion needed, just testing that it doesn't throw an exception
        $this->assertTrue(true);
    }
    
    public function testConnect(): void
    {
        // Connect to the database
        $result = $this->driver->connect();
        
        // Assert that the connection was successful
        $this->assertTrue($result);
        
        // Assert that the driver is connected
        $this->assertTrue($this->driver->isConnected());
    }
    
    public function testWrite(): void
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
    
    public function testWriteNotConnected(): void
    {
        // Create a data point
        $dataPoint = new DataPoint('test_measurement', ['value' => 42.0], ['tag' => 'test']);
        
        // Expect an exception when writing without being connected
        $this->expectException(WriteException::class);
        $this->expectExceptionMessage('Not connected to the database');
        
        $this->driver->write($dataPoint);
    }
    
    public function testClose(): void
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
