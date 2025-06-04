<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactory;
use TimeSeriesPhp\Drivers\RRDtool\Factory\TagStrategyFactory;
use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolQueryBuilder;
use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolRawQuery;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
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

    private string $dataDir;

    private string $rrdtoolPath = 'rrdtool'; // Assumes rrdtool is in PATH

    protected function setUp(): void
    {
        // Skip test if exec function is not available
        if (! function_exists('exec')) {
            $this->markTestSkipped('exec function is not available');
        }

        // Skip test if rrdtool is not available
        exec('which rrdtool', $output, $returnCode);
        if ($returnCode !== 0) {
            $this->markTestSkipped('rrdtool is not available');
        }
        $this->rrdtoolPath = trim($output[0]);

        // Use the data directory for RRD files
        $this->dataDir = rtrim(__DIR__.'/data', '/').'/';

        // Ensure the directory exists and is writable
        if (! is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }

        if (! is_writable($this->dataDir)) {
            $this->markTestSkipped('Data directory is not writable: '.$this->dataDir);
        }

        // Create a real RRDtoolConfig
        $config = new RRDtoolConfig(
            rrdtool_path: $this->rrdtoolPath,
            rrd_dir: $this->dataDir,
            use_rrdcached: false,
            persistent_process: false, // Use a smaller step for testing
            default_step: 60,
            debug: false,
            graph_output: 'file',
            tag_strategy: FileNameStrategy::class,
            default_archives: [
                'RRA:AVERAGE:0.5:1:1440',  // 1min for 1 day
                'RRA:MAX:0.5:1:1440',      // 1min max for 1 day
                'RRA:MIN:0.5:1:1440',      // 1min min for 1 day
            ],
        );

        $tag_strategy_class = $config->tag_strategy;
        $tagStrategy = new $tag_strategy_class($config);

        // Create a real RRDtoolDriver
        $this->driver = new RRDtoolDriver($config, new ProcessFactory, $tagStrategy, new RRDtoolQueryBuilder($tagStrategy), new NullLogger);
        $this->driver->connect();
    }

    protected function tearDown(): void
    {
        // Close the driver connection
        $this->driver->close();

        // Clean up RRD files but leave the directory
        $files = glob($this->dataDir.'*.rrd') ?: [];
        foreach ($files as $file) {
            unlink($file);
        }

        // Also clean up any graph files
        $graphFiles = glob($this->dataDir.'graph_*.png') ?: [];
        foreach ($graphFiles as $file) {
            unlink($file);
        }
    }

    public function test_connect(): void
    {
        $this->assertTrue($this->driver->isConnected());
    }

    public function test_rrd_path(): void
    {
        $file = $this->driver->getRRDPath('cpu_usage', ['host' => 'server1']);
        $this->assertEquals($this->dataDir.'cpu_usage_host-server1.rrd', $file);
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
        $rrdFile = $this->dataDir.'cpu_usage_host-server1.rrd';
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
        $now = new DateTime;
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
        $rrdFile1 = $this->dataDir.'memory_usage_host-server1.rrd';
        $rrdFile2 = $this->dataDir.'memory_usage_host-server2.rrd';
        $this->assertFileExists($rrdFile1);
        $this->assertFileExists($rrdFile2);
    }

    public function test_create_rrd_with_custom_config(): void
    {
        $rrdFile = $this->driver->getRRDPath('custom_metric', ['host' => 'server1']);
        $data_sources = [
            'DS:value:GAUGE:120:U:U',
            'DS:max:GAUGE:120:U:U',
        ];
        $archives = [
            'RRA:AVERAGE:0.5:1:1440',  // 1min for 1 day
            'RRA:MAX:0.5:1:1440',      // 1min max for 1 day
        ];

        $result = $this->driver->createRRDWithCustomConfig($rrdFile, $data_sources, 80, $archives);
        $this->assertTrue($result);

        // Verify the RRD file was created
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
        $now = new DateTime;
        $measurement = 'graph_test';
        $tags = ['host' => 'server1'];
        $rrdFile = $this->driver->getRRDPath($measurement, $tags);
        if (file_exists($rrdFile)) {
            unlink($rrdFile);
        }

        // First create and write to an RRD
        $dataPoint = new DataPoint(
            $measurement,
            ['value' => 23.5],
            $tags,
            $now
        );
        $this->driver->write($dataPoint);

        // Write another data point 60 seconds later
        $dataPoint = new DataPoint(
            $measurement,
            ['value' => 25.0],
            $tags,
            new DateTime('@'.($now->getTimestamp() + 60))
        );
        $this->driver->write($dataPoint);

        // Create a graph
        $rawQuery = (new RRDtoolRawQuery('graph'))
            ->param('--title', 'Test Graph')
            ->param('--vertical-label', 'Value')
            ->param('--imgformat', 'PNG')
            ->param('--width', '400')
            ->param('--height', '200')
            ->param('--start', $now->getTimestamp() - 120)
            ->param('--end', $now->getTimestamp() + 120)
            ->def('valueout', $rrdFile, 'value', 'AVERAGE')
            ->statement('LINE1', 'valueout#FF0000', 'Value');

        $outputPath = $this->driver->getRRDGraph($rawQuery);

        $this->assertFileExists($outputPath);
        $this->assertStringContainsString('.png', $outputPath);
        $this->assertEquals('image/png', mime_content_type($outputPath));
    }

    public function test_raw_query(): void
    {
        // First create and write to an RRD
        $now = new DateTime;
        $startTime = $now->getTimestamp();
        $measurement = 'query_test';
        $tags = ['host' => 'server1'];
        $rrdFile = $this->driver->getRRDPath($measurement, $tags);
        if (file_exists($rrdFile)) {
            unlink($rrdFile);
        }

        $dataPoint = new DataPoint(
            $measurement,
            ['value' => 23.5],
            $tags,
            $now
        );

        $this->driver->write($dataPoint);

        // Write another data point 60 seconds later
        $dataPoint = new DataPoint(
            $measurement,
            ['value' => 25.0],
            $tags,
            new DateTime('@'.($startTime + 60))
        );

        $this->driver->write($dataPoint);

        // Create a raw query
        $rawQuery = new RRDtoolRawQuery('xport');
        $rawQuery->param('-s', (string) $startTime)
            ->param('-e', (string) ($startTime + 120))
            ->def('val', $rrdFile, 'value', 'AVERAGE')
            ->xport('val', 'value');

        // The field name in the result will be 'value', not 'val'

        $result = $this->driver->rawQuery($rawQuery);

        $this->assertInstanceOf(QueryResult::class, $result);
        $series = $result->getSeries();
        $this->assertNotEmpty($series);

        // The result should contain our data points
        $this->assertArrayHasKey('value', $series);
    }

    public function test_create_database(): void
    {
        $result = $this->driver->createDatabase('test_db');
        $this->assertTrue($result);
        $this->assertDirectoryExists($this->dataDir.'test_db');
    }

    public function test_list_databases(): void
    {
        // Create a test database directory if it doesn't exist
        $testDbPath = $this->dataDir.'test_db';
        if (! is_dir($testDbPath)) {
            mkdir($testDbPath, 0777, true);
        }

        $databases = $this->driver->getDatabases();
        $this->assertContains('test_db', $databases);
    }
}
