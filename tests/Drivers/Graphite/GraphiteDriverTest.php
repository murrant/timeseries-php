<?php

namespace TimeSeriesPhp\Tests\Drivers\Graphite;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\RawQuery;
use TimeSeriesPhp\Drivers\Graphite\GraphiteConfig;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;
use TimeSeriesPhp\Drivers\Graphite\GraphiteQueryBuilder;
use TimeSeriesPhp\Exceptions\ConnectionException;

class GraphiteDriverTest extends TestCase
{
    private GraphiteDriver $driver;

    private GraphiteConfig $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(GraphiteConfig::class);

        // Configure the mock to return expected values
        $this->config->method('get')
            ->willReturnMap([
                ['host', null, 'localhost'],
                ['port', null, 2003],
                ['protocol', null, 'tcp'],
                ['timeout', null, 30],
                ['prefix', null, ''],
                ['batch_size', null, 500],
                ['web_host', null, 'localhost'],
                ['web_port', null, 8080],
                ['web_protocol', null, 'http'],
                ['web_path', null, '/render'],
            ]);

        $this->config->method('getWebUrl')
            ->willReturn('http://localhost:8080/render');

        // Create a real instance of GraphiteDriver with mocked methods
        $this->driver = new class extends GraphiteDriver {
            private $socket = null;

            protected function doConnect(): bool {
                $this->connected = true;
                $this->queryBuilder = new GraphiteQueryBuilder();
                return true;
            }

            protected function openSocket(): void {
                // Mock implementation that doesn't actually open a socket
                $this->socket = fopen('php://memory', 'r+');
            }

            protected function closeSocket(): void {
                // Mock implementation that doesn't actually close a socket
                if ($this->socket) {
                    fclose($this->socket);
                    $this->socket = null;
                }
            }

            public function write(\TimeSeriesPhp\Core\DataPoint $dataPoint): bool {
                // Mock implementation that checks connection status
                if (!$this->isConnected()) {
                    throw new \TimeSeriesPhp\Exceptions\ConnectionException('Not connected to Graphite');
                }
                return true;
            }

            public function writeBatch(array $dataPoints): bool {
                // Mock implementation that doesn't actually write to Graphite
                return true;
            }

            public function rawQuery(\TimeSeriesPhp\Core\RawQueryContract $query): \TimeSeriesPhp\Core\QueryResult {
                // Mock implementation that returns a predefined result
                return new \TimeSeriesPhp\Core\QueryResult([
                    ['target' => 'cpu.usage', 'timestamp' => 1672531200, 'value' => 10],
                    ['target' => 'cpu.usage', 'timestamp' => 1672534800, 'value' => 15],
                ]);
            }
        };
    }

    public function test_connect()
    {
        $result = $this->driver->connect($this->config);
        $this->assertTrue($result);
        $this->assertTrue($this->driver->isConnected());
    }

    public function test_write()
    {
        $this->driver->connect($this->config);

        $dataPoint = new DataPoint(
            'cpu',
            ['usage' => 23.5],
            ['host' => 'server01', 'region' => 'us-west'],
            new DateTime('2023-01-01 12:00:00')
        );

        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);
    }

    public function test_write_not_connected()
    {
        $this->expectException(ConnectionException::class);

        $dataPoint = new DataPoint(
            'cpu',
            ['usage' => 23.5],
            ['host' => 'server01']
        );

        $this->driver->write($dataPoint);
    }

    public function test_write_batch()
    {
        $this->driver->connect($this->config);

        $dataPoints = [
            new DataPoint(
                'cpu',
                ['usage' => 23.5],
                ['host' => 'server01']
            ),
            new DataPoint(
                'cpu',
                ['usage' => 25.0],
                ['host' => 'server02']
            ),
        ];

        $result = $this->driver->writeBatch($dataPoints);
        $this->assertTrue($result);
    }

    public function test_query()
    {
        $this->driver->connect($this->config);

        $query = new Query('cpu');
        $query->select(['usage'])
            ->where('host', '=', 'server01')
            ->timeRange(new DateTime('2023-01-01'), new DateTime('2023-01-02'));

        $result = $this->driver->query($query);

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertCount(2, $result->getSeries());
    }

    public function test_raw_query()
    {
        $this->driver->connect($this->config);

        $rawQuery = new RawQuery('target=cpu.usage&from=-1h&until=now&format=json');
        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertCount(2, $result->getSeries());
    }

    public function test_create_database()
    {
        $this->driver->connect($this->config);

        $result = $this->driver->createDatabase('test_db');
        $this->assertTrue($result);
    }

    public function test_list_databases()
    {
        $this->driver->connect($this->config);

        $databases = $this->driver->listDatabases();
        $this->assertIsArray($databases);
        $this->assertContains('default', $databases);
    }

    public function test_close()
    {
        $this->driver->connect($this->config);
        $this->driver->close();

        $this->assertFalse($this->driver->isConnected());
    }
}
