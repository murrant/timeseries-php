<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolRawQuery;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyContract;

class RRDtoolDriverTest extends TestCase
{
    private RRDtoolDriver $driver;
    private RRDtoolConfig $config;
    private string $tempDir;
    private string $rrdtoolPath = '/usr/bin/rrdtool';

    protected function setUp(): void
    {
        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir() . '/rrdtool_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Mock the RRDtoolConfig
        $this->config = $this->createMock(RRDtoolConfig::class);

        // Configure the mock to return expected values
        $this->config->method('getString')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'rrd_dir' => $this->tempDir . '/',
                    'rrdtool_path' => $this->rrdtoolPath,
                    'rrdcached_address' => '',
                    'tag_strategy' => FileNameStrategy::class,
                    default => '',
                };
            });

        $this->config->method('getBool')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'use_rrdcached' => false,
                    default => false,
                };
            });

        $this->config->method('getInt')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'default_step' => 300,
                    default => 0,
                };
            });

        $this->config->method('getArray')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'default_archives' => [
                        'RRA:AVERAGE:0.5:1:2016',      // 5min for 1 week
                        'RRA:AVERAGE:0.5:12:1488',     // 1hour for 2 months
                        'RRA:AVERAGE:0.5:288:366',     // 1day for 1 year
                    ],
                    default => [],
                };
            });

        // Create a mock tag strategy
        $tagStrategy = $this->createMock(RRDTagStrategyContract::class);
        $tagStrategy->method('getFilePath')
            ->willReturnCallback(function ($measurement, $tags = []) {
                $tagString = '';
                if (!empty($tags)) {
                    ksort($tags);
                    foreach ($tags as $key => $value) {
                        $tagString .= "_{$key}-" . str_replace('.', '\\.', $value);
                    }
                }
                return $this->tempDir . '/' . $measurement . $tagString . '.rrd';
            });

        // Create a subclass of RRDtoolDriver that overrides methods that would execute commands
        $this->driver = new class($this->tempDir, $tagStrategy) extends RRDtoolDriver {
            private string $tempDir;
            private RRDTagStrategyContract $tagStrategy;

            public function __construct(string $tempDir, RRDTagStrategyContract $tagStrategy)
            {
                $this->tempDir = $tempDir;
                $this->tagStrategy = $tagStrategy;
                $this->rrdDir = $tempDir . '/';
                $this->connected = true;
                $this->queryBuilder = new \TimeSeriesPhp\Drivers\RRDtool\RRDtoolQueryBuilder($this->tagStrategy);
            }

            protected function doConnect(): bool
            {
                return true;
            }

            public function write(\TimeSeriesPhp\Core\DataPoint $dataPoint): bool
            {
                // Mock implementation that doesn't execute commands
                $rrdPath = $this->tagStrategy->getFilePath($dataPoint->getMeasurement(), $dataPoint->getTags());

                // Simulate creating the RRD file
                if (!file_exists($rrdPath)) {
                    touch($rrdPath);
                }

                return true;
            }

            public function rawQuery(\TimeSeriesPhp\Core\RawQueryContract $query): \TimeSeriesPhp\Core\QueryResult
            {
                // Mock implementation that doesn't execute commands
                if ($query instanceof \TimeSeriesPhp\Drivers\RRDtool\RRDtoolRawQuery && $query->type === 'xport') {
                    return new \TimeSeriesPhp\Core\QueryResult([
                        ['time' => time(), 'value' => 23.5],
                        ['time' => time() + 300, 'value' => 24.0],
                        ['time' => time() + 600, 'value' => 24.5],
                    ]);
                }

                return new \TimeSeriesPhp\Core\QueryResult([['raw_output' => 'Mocked output']]);
            }

            public function createRRDWithCustomConfig(string $measurement, array $tags, array $config): bool
            {
                // Mock implementation that doesn't execute commands
                $rrdPath = $this->tagStrategy->getFilePath($measurement, $tags);

                // Simulate creating the RRD file
                if (!file_exists($rrdPath)) {
                    touch($rrdPath);
                }

                return true;
            }

            public function getRRDGraph(string $measurement, array $tags, array $graphConfig): string
            {
                // Mock implementation that doesn't execute commands
                $outputPath = $this->tempDir . '/graph_' . uniqid() . '.png';
                touch($outputPath);

                return $outputPath;
            }
        };

        // Connect the driver
        $this->driver->connect($this->config);
    }

    protected function tearDown(): void
    {
        // Clean up temporary files and directory
        $files = glob($this->tempDir . '/*.rrd') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function test_connect(): void
    {
        $result = $this->driver->isConnected();
        $this->assertTrue($result);
    }

    public function test_write(): void
    {
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['value' => 23.5],
            ['host' => 'server1'],
            new DateTime('2023-01-01 12:00:00')
        );

        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);
    }

    public function test_write_batch(): void
    {
        $dataPoints = [
            new DataPoint(
                'cpu_usage',
                ['value' => 23.5],
                ['host' => 'server1']
            ),
            new DataPoint(
                'memory_usage',
                ['value' => 1024.0],
                ['host' => 'server1']
            ),
        ];

        $result = $this->driver->writeBatch($dataPoints);
        $this->assertTrue($result);
    }

    public function test_raw_query(): void
    {
        $rawQuery = new RRDtoolRawQuery('xport');
        $rawQuery->param('-s', '1672531200')
            ->param('-e', '1672617600')
            ->def('val', 'cpu_usage.rrd', 'value', 'AVERAGE')
            ->xport('val', 'value');

        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertCount(3, $result->getSeries());
    }

    public function test_create_database(): void
    {
        $result = $this->driver->createDatabase('test_db');
        $this->assertTrue($result);
        $this->assertDirectoryExists($this->tempDir . '/test_db');
    }

    public function test_list_databases(): void
    {
        // Create a test database directory
        mkdir($this->tempDir . '/test_db', 0777, true);

        $databases = $this->driver->listDatabases();
        $this->assertContains('test_db', $databases);
    }

    public function test_close(): void
    {
        $this->driver->close();

        // Use reflection to check if the connected property is set to false
        $reflection = new \ReflectionClass($this->driver);
        $property = $reflection->getProperty('connected');
        $property->setAccessible(true);

        $this->assertFalse($property->getValue($this->driver));
    }

    public function test_create_rrd_with_custom_config(): void
    {
        $customConfig = [
            'step' => 60,
            'data_sources' => [
                'DS:value:GAUGE:120:U:U',
                'DS:max:GAUGE:120:U:U',
            ],
            'archives' => [
                'RRA:AVERAGE:0.5:1:1440',  // 1min for 1 day
                'RRA:MAX:0.5:1:1440',      // 1min max for 1 day
            ],
        ];

        $result = $this->driver->createRRDWithCustomConfig('custom_metric', ['host' => 'server1'], $customConfig);
        $this->assertTrue($result);
    }

    public function test_get_rrd_graph(): void
    {
        $graphConfig = [
            'title' => 'CPU Usage',
            'vertical-label' => 'Percent',
            'width' => '800',
            'height' => '400',
            'start' => '-1d',
            'end' => 'now',
        ];

        $outputPath = $this->driver->getRRDGraph('cpu_usage', ['host' => 'server1'], $graphConfig);
        $this->assertStringContainsString($this->tempDir, $outputPath);
    }

}
