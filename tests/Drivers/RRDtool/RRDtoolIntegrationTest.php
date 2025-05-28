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

/**
 * Integration test for RRDtoolDriver that assumes rrdtool is available
 * and creates real RRD artifacts in tests/Drivers/RRDtool/data.
 * 
 * @group integration
 */
class RRDtoolIntegrationTest extends TestCase
{
    private RRDtoolDriver $driver;
    private RRDtoolConfig $config;
    private string $dataDir;
    private string $rrdtoolPath = 'rrdtool'; // Assumes rrdtool is in PATH

    protected function setUp(): void
    {
        // Skip test if exec function is not available
        if (!function_exists('exec')) {
            $this->markTestSkipped('exec function is not available');
        }

        // Skip test if rrdtool is not available
        exec('which rrdtool', $output, $returnCode);
        if ($returnCode !== 0) {
            $this->markTestSkipped('rrdtool is not available');
        }
        $this->rrdtoolPath = trim($output[0]);

        // Use the data directory for RRD files
        $this->dataDir = rtrim(__DIR__ . '/data', '/') . '/';

        // Ensure the directory exists and is writable
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }

        if (!is_writable($this->dataDir)) {
            $this->markTestSkipped('Data directory is not writable: ' . $this->dataDir);
        }

        // Create a real RRDtoolConfig
        $this->config = new RRDtoolConfig([
            'rrd_dir' => $this->dataDir,
            'rrdtool_path' => $this->rrdtoolPath,
            'use_rrdcached' => false,
            'default_step' => 60, // Use a smaller step for testing
            'tag_strategy' => FileNameStrategy::class,
            'default_archives' => [
                'RRA:AVERAGE:0.5:1:1440',  // 1min for 1 day
                'RRA:MAX:0.5:1:1440',      // 1min max for 1 day
                'RRA:MIN:0.5:1:1440',      // 1min min for 1 day
            ],
        ]);

        // Create a real RRDtoolDriver
        $this->driver = new RRDtoolDriver();
        $this->driver->connect($this->config);
    }

    protected function tearDown(): void
    {
        // Close the driver connection
        $this->driver->close();

        // Clean up RRD files but leave the directory
        $files = glob($this->dataDir . '*.rrd') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }

        // Also clean up any graph files
        $graphFiles = glob($this->dataDir . 'graph_*.png') ?: [];
        foreach ($graphFiles as $file) {
            unlink($file);
        }
    }

    public function test_connect(): void
    {
        $this->assertTrue($this->driver->isConnected());
    }

    public function test_create_and_write_to_rrd(): void
    {
        // Create a data point
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['value' => 23.5],
            ['host' => 'server1'],
            new DateTime('now')
        );

        // Write the data point
        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);

        // Verify the RRD file was created
        $rrdFile = $this->dataDir . 'cpu_usage_host-server1.rrd';
        $this->assertFileExists($rrdFile);

        // Write another data point 60 seconds later
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['value' => 25.0],
            ['host' => 'server1'],
            new DateTime('+60 seconds')
        );

        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);
    }

    public function test_write_batch(): void
    {
        // Create multiple data points
        $now = new DateTime();
        $dataPoints = [
            new DataPoint(
                'memory_usage',
                ['value' => 1024.0],
                ['host' => 'server1'],
                $now
            ),
            new DataPoint(
                'memory_usage',
                ['value' => 2048.0],
                ['host' => 'server2'],
                $now
            ),
        ];

        // Write the batch
        $result = $this->driver->writeBatch($dataPoints);
        $this->assertTrue($result);

        // Verify the RRD files were created
        $rrdFile1 = $this->dataDir . 'memory_usage_host-server1.rrd';
        $rrdFile2 = $this->dataDir . 'memory_usage_host-server2.rrd';
        $this->assertFileExists($rrdFile1);
        $this->assertFileExists($rrdFile2);
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

        // Verify the RRD file was created
        $rrdFile = $this->dataDir . 'custom_metric_host-server1.rrd';
        $this->assertFileExists($rrdFile);

        // Write data to the custom RRD
        $dataPoint = new DataPoint(
            'custom_metric',
            ['value' => 23.5, 'max' => 30.0],
            ['host' => 'server1'],
            new DateTime('now')
        );

        $result = $this->driver->write($dataPoint);
        $this->assertTrue($result);
    }

    public function test_get_rrd_graph(): void
    {
        // First create and write to an RRD
        $now = new DateTime();
        $dataPoint = new DataPoint(
            'graph_test',
            ['value' => 23.5],
            ['host' => 'server1'],
            $now
        );

        $this->driver->write($dataPoint);

        // Write another data point 60 seconds later
        $dataPoint = new DataPoint(
            'graph_test',
            ['value' => 25.0],
            ['host' => 'server1'],
            new DateTime('@' . ($now->getTimestamp() + 60))
        );

        $this->driver->write($dataPoint);

        // Create a graph
        $graphConfig = [
            'title' => 'Test Graph',
            'vertical-label' => 'Value',
            'width' => '400',
            'height' => '200',
            'start' => $now->getTimestamp(),
            'end' => $now->getTimestamp() + 120,
        ];

        $outputPath = $this->driver->getRRDGraph('graph_test', ['host' => 'server1'], $graphConfig);

        $this->assertFileExists($outputPath);
        $this->assertStringContainsString('.png', $outputPath);
    }

    public function test_raw_query(): void
    {
        // First create and write to an RRD
        $now = new DateTime();
        $startTime = $now->getTimestamp();

        $dataPoint = new DataPoint(
            'query_test',
            ['value' => 23.5],
            ['host' => 'server1'],
            $now
        );

        $this->driver->write($dataPoint);

        // Write another data point 60 seconds later
        $dataPoint = new DataPoint(
            'query_test',
            ['value' => 25.0],
            ['host' => 'server1'],
            new DateTime('@' . ($startTime + 60))
        );

        $this->driver->write($dataPoint);

        // Create a raw query
        $rawQuery = new RRDtoolRawQuery('xport');
        $rawQuery->param('-s', (string)$startTime)
            ->param('-e', (string)($startTime + 120))
            ->def('val', $this->dataDir . 'query_test_host-server1.rrd', 'value', 'AVERAGE')
            ->xport('val', 'value');

        // The field name in the result will be 'value', not 'val'

        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
        $series = $result->getSeries();
        $this->assertNotEmpty($series);

        // The result should contain our data points
        $this->assertArrayHasKey('value', $series[0]);
    }

    public function test_create_database(): void
    {
        $result = $this->driver->createDatabase('test_db');
        $this->assertTrue($result);
        $this->assertDirectoryExists($this->dataDir . 'test_db');
    }

    public function test_list_databases(): void
    {
        // Create a test database directory if it doesn't exist
        $testDbPath = $this->dataDir . 'test_db';
        if (!is_dir($testDbPath)) {
            mkdir($testDbPath, 0777, true);
        }

        $databases = $this->driver->listDatabases();
        $this->assertContains('test_db', $databases);
    }
}
