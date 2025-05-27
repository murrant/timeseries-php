<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;

class InfluxDBDriverTest extends TestCase
{
    private InfluxDBDriver $driver;

    private InfluxDBConfig $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(InfluxDBConfig::class);

        // Configure the mock to return expected values
        $this->config->method('get')
            ->willReturnMap([
                ['url', null, 'http://localhost:8086'],
                ['token', null, 'test-token'],
                ['org', null, 'test-org'],
                ['bucket', null, 'test-bucket'],
                ['timeout', null, 30],
                ['verify_ssl', null, true],
                ['debug', null, false],
            ]);

        $this->config->method('getClientConfig')
            ->willReturn([
                'url' => 'http://localhost:8086',
                'token' => 'test-token',
                'bucket' => 'test-bucket',
                'org' => 'test-org',
                'timeout' => 30,
                'verifySSL' => true,
                'debug' => false,
            ]);

        // Create a partial mock of InfluxDBDriver to mock abstract methods
        $this->driver = $this->getMockBuilder(InfluxDBDriver::class)
            ->setMethods(['doConnect', 'executeQuery'])
            ->getMock();

        // Configure the mock to return true for doConnect
        $this->driver->method('doConnect')
            ->willReturn(true);

        // Configure the mock to return sample data for executeQuery
        $this->driver->method('executeQuery')
            ->willReturn([
                ['time' => '2023-01-01T00:00:00Z', 'value' => 10],
                ['time' => '2023-01-01T01:00:00Z', 'value' => 15],
            ]);
    }

    public function test_connect()
    {
        $result = $this->driver->connect($this->config);
        $this->assertTrue($result);
    }

    public function test_query()
    {
        $query = new Query('cpu_usage');
        $query->select(['usage_user', 'usage_system'])
            ->where('host', 'server01')
            ->timeRange(new DateTime('2023-01-01'), new DateTime('2023-01-02'));

        $result = $this->driver->query($query);

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertCount(2, $result->getSeries());
    }

    public function test_raw_query()
    {
        $result = $this->driver->rawQuery('SELECT * FROM cpu_usage');

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertCount(2, $result->getSeries());
    }

    public function test_write()
    {
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['usage_user' => 23.5, 'usage_system' => 12.1],
            ['host' => 'server01', 'region' => 'us-west'],
            new DateTime('2023-01-01 12:00:00')
        );

        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);
    }

    public function test_write_batch()
    {
        $dataPoints = [
            new DataPoint(
                'cpu_usage',
                ['usage_user' => 23.5],
                ['host' => 'server01']
            ),
            new DataPoint(
                'cpu_usage',
                ['usage_user' => 25.0],
                ['host' => 'server02']
            ),
        ];

        $result = $this->driver->writeBatch($dataPoints);
        $this->assertTrue($result);
    }

    public function test_create_database()
    {
        $result = $this->driver->createDatabase('test_db');
        $this->assertTrue($result);
    }

    public function test_list_databases()
    {
        $databases = $this->driver->listDatabases();
        $this->assertIsArray($databases);
        $this->assertContains('mydb', $databases);
        $this->assertContains('testdb', $databases);
    }

    public function test_close()
    {
        $this->driver->close();

        // Use reflection to check if the connected property is set to false
        $reflection = new \ReflectionClass($this->driver);
        $property = $reflection->getProperty('connected');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($this->driver));
    }
}
