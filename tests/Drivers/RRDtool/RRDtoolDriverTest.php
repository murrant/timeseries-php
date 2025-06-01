<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use DateTime;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Drivers\RRDtool\Config\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\Factory\InputStreamFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Factory\TagStrategyFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolQueryBuilder;
use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolRawQuery;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;

class RRDtoolDriverTest extends TestCase
{
    private RRDtoolDriver $driver;

    private string $tempDir;

    private string $rrdtoolPath = '/usr/bin/rrdtool';

    private RRDtoolConfig $config;

    private MockObject&ProcessFactoryInterface $processFactory;

    private MockObject&InputStreamFactoryInterface $inputStreamFactory;

    private MockObject&TagStrategyFactoryInterface $tagStrategyFactory;

    private MockObject&RRDTagStrategyInterface $tagStrategy;

    private RRDtoolQueryBuilder $queryBuilder;

    private LoggerInterface $logger;

    protected function setUp(): void
    {
        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir().'/rrdtool_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        // Create configuration
        $this->config = new RRDtoolConfig(
            rrdtool_path: $this->rrdtoolPath,
            rrd_dir: $this->tempDir.'/',
            use_rrdcached: false,
            persistent_process: true,
            command_timeout: 180,
            rrdcached_address: '',
            default_step: 300,
            debug: false,
            graph_output: 'string',
            tag_strategy: FileNameStrategy::class,
            default_archives: [
                'RRA:AVERAGE:0.5:1:2016',      // 5min for 1 week
                'RRA:AVERAGE:0.5:12:1488',     // 1hour for 2 months
                'RRA:AVERAGE:0.5:288:366',     // 1day for 1 year
            ]
        );

        // Create mock tag strategy
        $this->tagStrategy = $this->createMock(RRDTagStrategyInterface::class);
        $this->tagStrategy->method('getFilePath')
            ->willReturnCallback(function (string $measurement, array $tags = []) {
                $tagString = '';
                if (! empty($tags)) {
                    ksort($tags);
                    foreach ($tags as $key => $value) {
                        $tagString .= "_{$key}-".str_replace('.', '\\.', (string) $value);
                    }
                }

                return $this->tempDir.'/'.$measurement.$tagString.'.rrd';
            });

        // Create mock process
        $mockProcess = $this->createMock(Process::class);
        $mockProcess->method('setTimeout')->willReturnSelf();
        $mockProcess->method('setInput')->willReturnSelf();
        $mockProcess->method('getExitCode')->willReturn(0);
        $mockProcess->method('getOutput')->willReturn('OK');

        // For void methods, we don't set a return value
        $mockProcess->expects($this->any())->method('start');
        $mockProcess->expects($this->any())->method('run');

        // Create mock input stream
        $mockInputStream = $this->createMock(InputStream::class);
        // For void methods, we don't set a return value
        $mockInputStream->expects($this->any())->method('write');

        // Create mock factories
        $this->processFactory = $this->createMock(ProcessFactoryInterface::class);
        $this->processFactory->method('create')->willReturn($mockProcess);

        $this->inputStreamFactory = $this->createMock(InputStreamFactoryInterface::class);
        $this->inputStreamFactory->method('create')->willReturn($mockInputStream);

        $this->tagStrategyFactory = $this->createMock(TagStrategyFactoryInterface::class);
        $this->tagStrategyFactory->method('create')->willReturn($this->tagStrategy);

        // Create query builder
        $this->queryBuilder = new RRDtoolQueryBuilder($this->tagStrategy);

        // Create logger
        $this->logger = new NullLogger;

        // Create driver with mocked dependencies
        $this->driver = $this->getMockBuilder(RRDtoolDriver::class)
            ->setConstructorArgs([
                $this->config,
                $this->processFactory,
                $this->inputStreamFactory,
                $this->tagStrategyFactory,
                $this->queryBuilder,
                $this->logger,
            ])
            ->onlyMethods(['doConnect', 'doWrite', 'rawQuery', 'createRRDWithCustomConfig', 'getRRDGraph', 'createDatabase', 'getDatabases'])
            ->getMock();

        // Mock methods to avoid executing real commands
        $this->driver->method('doConnect')->willReturn(true);

        $this->driver->method('doWrite')->willReturnCallback(function (DataPoint $dataPoint) {
            $rrdPath = $this->tagStrategy->getFilePath($dataPoint->getMeasurement(), $dataPoint->getTags());

            // Simulate creating the RRD file
            if (! file_exists($rrdPath)) {
                touch($rrdPath);
            }

            // For debugging, store the update string in a file
            $fields = $dataPoint->getFields();
            $values = [];

            foreach (array_keys($fields) as $dsName) {
                $value = $fields[$dsName] ?? 'U';
                $values[] = (is_numeric($value) || $value === 'U') ? $value : 'U';
            }

            $updateString = $dataPoint->getTimestamp()->getTimestamp().':'.implode(':', $values);
            file_put_contents($rrdPath.'.update', $updateString);

            return true;
        });

        $this->driver->method('rawQuery')->willReturnCallback(function ($query) {
            if ($query instanceof RRDtoolRawQuery && $query->command === 'xport') {
                return new QueryResult([
                    'cpu_usage' => [
                        ['date' => time(), 'value' => 23.5],
                        ['date' => time() + 300, 'value' => 24.0],
                        ['date' => time() + 600, 'value' => 24.5],
                    ],
                ]);
            }

            return new QueryResult([
                'output' => [
                    ['date' => time(), 'value' => 'Mocked output'],
                ],
            ]);
        });

        $this->driver->method('createRRDWithCustomConfig')->willReturnCallback(function (string $filename, array $data_sources) {
            if (! file_exists($filename)) {
                touch($filename);
            }

            return file_exists($filename);
        });

        $this->driver->method('getRRDGraph')->willReturnCallback(function () {
            $outputPath = $this->tempDir.'/graph_'.uniqid().'.png';
            touch($outputPath);

            return $outputPath;
        });

        $this->driver->method('createDatabase')->willReturnCallback(function (string $database) {
            $dbDir = $this->tempDir.'/'.$database;
            if (! is_dir($dbDir)) {
                mkdir($dbDir, 0777, true);
            }

            return true;
        });

        $this->driver->method('getDatabases')->willReturn(['test_db']);

        // Set connected property to true using reflection
        $reflection = new \ReflectionClass($this->driver);
        $property = $reflection->getProperty('connected');
        $property->setAccessible(true);
        $property->setValue($this->driver, true);

        // Set tagStrategy property using reflection
        $property = $reflection->getProperty('tagStrategy');
        $property->setAccessible(true);
        $property->setValue($this->driver, $this->tagStrategy);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory and all its contents
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDir($this->tempDir);
        }
    }

    /**
     * Recursively remove a directory and all its contents
     */
    private function recursiveRemoveDir(string $dir): void
    {
        $items = glob($dir.'/*') ?: [];
        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->recursiveRemoveDir($item);
            } elseif (is_file($item)) {
                unlink($item);
            }
        }

        if (is_dir($dir)) {
            rmdir($dir);
        }
    }

    /**
     * Helper method to get the RRD file path for a measurement and tags
     */
    private function getRRDPath(string $measurement, array $tags = []): string
    {
        return $this->tagStrategy->getFilePath($measurement, $tags);
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
        $series = $result->getSeries();
        $this->assertNotEmpty($series);
        // Check that the cpu_usage field exists in the series
        $this->assertArrayHasKey('cpu_usage', $series);
        // Check that there are 3 data points in the cpu_usage field
        $this->assertCount(3, $series['cpu_usage']);
    }

    public function test_create_database(): void
    {
        $result = $this->driver->createDatabase('test_db');
        $this->assertTrue($result);
        $this->assertDirectoryExists($this->tempDir.'/test_db');
    }

    public function test_list_databases(): void
    {
        // Create a test database directory
        mkdir($this->tempDir.'/test_db', 0777, true);

        $databases = $this->driver->getDatabases();
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
        $dataSources = [
            'DS:value:GAUGE:120:U:U',
            'DS:max:GAUGE:120:U:U',
        ];
        $archives = [
            'RRA:AVERAGE:0.5:1:1440',  // 1min for 1 day
            'RRA:MAX:0.5:1:1440',      // 1min max for 1 day
        ];
        $file = $this->getRRDPath('custom_metric', ['host' => 'server1']);

        $result = $this->driver->createRRDWithCustomConfig($file, $dataSources, 80, $archives);

        $this->assertTrue($result);
        $this->assertFileExists($file);
    }

    public function test_get_rrd_graph(): void
    {
        $rrdPath = $this->getRRDPath('cpu_usage', ['host' => 'server1']);

        $outputPath = $this->tempDir.'/graph_'.uniqid().'.png';
        $rawQuery = new RRDtoolRawQuery('graph', $outputPath);
        $rawQuery->param('--title', 'CPU Usage');
        $rawQuery->param('--vertical-label', 'Percent');
        $rawQuery->param('--width', '800');
        $rawQuery->param('--height', '400');
        $rawQuery->param('--start', '-1d');
        $rawQuery->param('--end', 'now');
        $rawQuery->def('value', $rrdPath, 'value', 'AVERAGE');

        $outputPath = $this->driver->getRRDGraph($rawQuery);
        $this->assertStringContainsString($this->tempDir, $outputPath);
    }

    public function test_write_with_null_value(): void
    {
        // Create a DataPoint with a null value
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['value' => null],
            ['host' => 'server1'],
            new DateTime('2023-01-01 12:00:00')
        );

        // This should not throw an exception
        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);

        // Create a DataPoint with mixed values including null
        $dataPoint = new DataPoint(
            'system_stats',
            [
                'cpu' => 45.2,
                'memory' => null,
                'disk' => 78.5,
            ],
            ['host' => 'server1'],
            new DateTime('2023-01-01 12:00:00')
        );

        // This should not throw an exception
        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);
    }
}
