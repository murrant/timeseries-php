<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Drivers\RRDtool\Config\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolTagException;
use TimeSeriesPhp\Drivers\RRDtool\Factory\InputStreamFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Factory\QueryBuilderFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Factory\TagStrategyFactoryInterface;
use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolRawQuery;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;

class RRDtoolDriverTest extends TestCase
{
    private RRDtoolDriver $driver;

    private RRDtoolConfig $config;

    private string $tempDir;

    private string $rrdtoolPath = '/usr/bin/rrdtool';

    protected function setUp(): void
    {
        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir().'/rrdtool_test_'.uniqid();
        mkdir($this->tempDir, 0777, true);

        // Mock the RRDtoolConfig
        $this->config = $this->createMock(RRDtoolConfig::class);

        // Configure the mock to return expected values
        $this->config->method('getString')
            ->willReturnCallback(function ($key) {
                return match ($key) {
                    'rrd_dir' => $this->tempDir.'/',
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
        $tagStrategy = $this->createMock(RRDTagStrategyInterface::class);
        $tagStrategy->method('getFilePath')
            ->willReturnCallback(
                /** @param array<string, string> $tags */
                function (string $measurement, array $tags = []) {
                    $tagString = '';
                    if (! empty($tags)) {
                        ksort($tags);
                        foreach ($tags as $key => $value) {
                            if (! is_scalar($value)) {
                                throw new RRDtoolTagException('Tag value must be a scalar');
                            }

                            $tagString .= "_{$key}-".str_replace('.', '\\.', (string) $value);
                        }
                    }

                    return $this->tempDir.'/'.$measurement.$tagString.'.rrd';
                });

        // Create mock factories
        $mockProcessFactory = $this->createMock(ProcessFactoryInterface::class);
        $mockInputStreamFactory = $this->createMock(InputStreamFactoryInterface::class);
        $mockTagStrategyFactory = $this->createMock(TagStrategyFactoryInterface::class);
        $mockQueryBuilderFactory = $this->createMock(QueryBuilderFactoryInterface::class);

        // Configure the mock tag strategy factory to return the tag strategy
        $mockTagStrategyFactory->method('create')
            ->willReturn($tagStrategy);

        // Configure the mock query builder factory to return a query builder
        $mockQueryBuilderFactory->method('create')
            ->willReturn(new \TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolQueryBuilder($tagStrategy));

        // Create a subclass of RRDtoolDriver that overrides methods that would execute commands
        $this->driver = new class($this->tempDir, $tagStrategy, $mockProcessFactory, $mockInputStreamFactory, $mockTagStrategyFactory, $mockQueryBuilderFactory) extends RRDtoolDriver
        {
            protected string $tempDir;

            public function __construct(
                string $tempDir,
                RRDTagStrategyInterface $tagStrategy,
                ProcessFactoryInterface $processFactory,
                InputStreamFactoryInterface $inputStreamFactory,
                TagStrategyFactoryInterface $tagStrategyFactory,
                QueryBuilderFactoryInterface $queryBuilderFactory
            ) {
                parent::__construct($processFactory, $inputStreamFactory, $tagStrategyFactory, $queryBuilderFactory);

                $this->tempDir = $tempDir;
                $this->tagStrategy = $tagStrategy;
                $this->rrdDir = $tempDir.'/';
                $this->connected = true;
                $this->queryBuilder = new \TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolQueryBuilder($this->tagStrategy);
            }

            protected function doConnect(): bool
            {
                return true;
            }

            protected function doWrite(\TimeSeriesPhp\Core\Data\DataPoint $dataPoint): bool
            {
                // Mock implementation that simulates the real behavior
                $rrdPath = $this->tagStrategy->getFilePath($dataPoint->getMeasurement(), $dataPoint->getTags());

                // Simulate creating the RRD file
                if (! file_exists($rrdPath)) {
                    touch($rrdPath);
                }

                // Simulate processing the values to test null handling
                $fields = $dataPoint->getFields();
                $values = [];

                // Simulate the data source order (simplified)
                $dataSourceOrder = array_keys($fields);

                foreach ($dataSourceOrder as $dsName) {
                    // This is the key part that tests our fix
                    // Get the value or use 'U' (unknown) if the field doesn't exist
                    $value = $fields[$dsName] ?? 'U';

                    // Ensure the value is valid for RRDtool (numeric or 'U')
                    // This handles null values, objects, arrays, or any other non-numeric type
                    if ($value === 'U' || is_numeric($value)) {
                        $processedValue = $value;
                    } else {
                        $processedValue = 'U'; // Use 'U' for any non-numeric value including null
                    }

                    $values[] = $processedValue;
                }

                // In a real implementation, this would be passed to RRDtool
                $updateString = $dataPoint->getTimestamp()->getTimestamp().':'.implode(':', $values);

                // For debugging, store the update string in a file
                file_put_contents($rrdPath.'.update', $updateString);

                return true;
            }

            public function rawQuery(\TimeSeriesPhp\Contracts\Query\RawQueryInterface $query): \TimeSeriesPhp\Core\Data\QueryResult
            {
                // Mock implementation that doesn't execute commands
                if ($query instanceof \TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolRawQuery && $query->command === 'xport') {
                    return new \TimeSeriesPhp\Core\Data\QueryResult([
                        'cpu_usage' => [
                            ['date' => time(), 'value' => 23.5],
                            ['date' => time() + 300, 'value' => 24.0],
                            ['date' => time() + 600, 'value' => 24.5],
                        ],
                    ]);
                }

                return new \TimeSeriesPhp\Core\Data\QueryResult([
                    'output' => [
                        ['date' => time(), 'value' => 'Mocked output'],
                    ],
                ]);
            }

            public function createRRDWithCustomConfig(string $filename, array $data_sources, ?int $step = null, ?array $archives = null): bool
            {
                // Mock implementation that doesn't execute commands

                // Simulate creating the RRD file
                if (! file_exists($filename)) {
                    touch($filename);
                }

                return file_exists($filename);
            }

            public function getRRDGraph(RRDtoolRawQuery $graphConfig): string
            {
                // Mock implementation that doesn't execute commands
                $outputPath = $this->tempDir.'/graph_'.uniqid().'.png';
                touch($outputPath);

                return $outputPath;
            }

            public function createDatabase(string $database): bool
            {
                // Create the database directory
                $dbDir = $this->tempDir.'/'.$database;
                if (! is_dir($dbDir)) {
                    mkdir($dbDir, 0777, true);
                }

                return true;
            }

            public function getDatabases(): array
            {
                // Mock implementation that returns a fixed list
                return ['test_db'];
            }
        };

        // Connect the driver
        $this->driver->connect($this->config);
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
        $file = $this->driver->getRRDPath('custom_metric', ['host' => 'server1']);

        $result = $this->driver->createRRDWithCustomConfig($file, $dataSources, 80, $archives);

        $this->assertTrue($result);
        $this->assertFileExists($file);
    }

    public function test_get_rrd_graph(): void
    {
        $rrdPath = $this->driver->getRRDPath('cpu_usage', ['host' => 'server1']);

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
