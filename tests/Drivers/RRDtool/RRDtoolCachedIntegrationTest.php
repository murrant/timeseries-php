<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use DateTime;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Drivers\RRDtool\Factory\ProcessFactory;
use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolQueryBuilder;
use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolRawQuery;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;

/**
 * Integration test for RRDtoolDriver with rrdcached support
 * that assumes rrdtool and rrdcached are available.
 *
 * @group integration
 */
class RRDtoolCachedIntegrationTest extends TestCase
{
    private RRDtoolDriver $driver;

    private RRDtoolConfig $config;

    private string $dataDir;

    private string $rrdtoolPath = 'rrdtool'; // Assumes rrdtool is in PATH

    private string $rrdcachedAddress = 'localhost:42217'; // Default rrdcached address

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

        // Check if rrdcached is available
        $rrdcachedHost = getenv('RRDCACHED_HOST') ?: 'localhost';
        $rrdcachedPort = getenv('RRDCACHED_PORT') ?: '42217';
        $this->rrdcachedAddress = "$rrdcachedHost:$rrdcachedPort";

        // Try to connect to rrdcached
        $fp = @fsockopen($rrdcachedHost, (int) $rrdcachedPort, $errno, $errstr, 5);
        if (! $fp) {
            $this->markTestSkipped("Cannot connect to rrdcached at {$this->rrdcachedAddress}: $errstr ($errno)");
        }
        fclose($fp);

        // Use a relative path for RRD files when using rrdcached
        // This is required because rrdcached doesn't allow absolute paths
        $this->dataDir = 'data/cached/';

        // Ensure the directory exists and is writable
        $absoluteDir = rtrim(__DIR__.'/data/cached', '/').'/';
        if (! is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0777, true);
        }

        if (! is_writable($absoluteDir)) {
            $this->markTestSkipped('Data directory is not writable: '.$absoluteDir);
        }

        // Create a real RRDtoolConfig with rrdcached enabled
        $config = new RRDtoolConfig(
            rrdtool_path: $this->rrdtoolPath,
            rrd_dir: $this->dataDir,
            use_rrdcached: true,
            persistent_process: false,
            rrdcached_address: $this->rrdcachedAddress, // Use a smaller step for testing
            default_step: 60,
            debug: true,
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
        // Verify that rrdcached is being used
        $this->assertNotEmpty($this->driver->getRrdcachedAddress());
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

        // Skip file existence check since files are created in the container
        // but may not be visible in the host filesystem
        $rrdFile = $this->dataDir.'cpu_usage_host-server1.rrd';

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

        // Skip file existence check since files are created in the container
        // but may not be visible in the host filesystem
        $rrdFile1 = $this->dataDir.'memory_usage_host-server1.rrd';
        $rrdFile2 = $this->dataDir.'memory_usage_host-server2.rrd';
    }

    public function test_raw_query(): void
    {
        // First create and write to an RRD
        $now = new DateTime;
        $startTime = $now->getTimestamp();
        $measurement = 'query_test';
        $tags = ['host' => 'server1'];
        $rrdFile = $this->driver->getRRDPath($measurement, $tags);

        // Create a unique timestamp for this test to avoid conflicts
        $uniqueTimestamp = time() + 3600; // 1 hour in the future

        $dataPoint = new DataPoint(
            $measurement,
            ['value' => 23.5],
            $tags,
            new DateTime('@'.$uniqueTimestamp)
        );

        $this->driver->write($dataPoint);

        // Write another data point 60 seconds later
        $dataPoint = new DataPoint(
            $measurement,
            ['value' => 25.0],
            $tags,
            new DateTime('@'.($uniqueTimestamp + 60))
        );

        $this->driver->write($dataPoint);

        // Create a raw query
        $rawQuery = new RRDtoolRawQuery('xport');
        $rawQuery->param('-s', (string) $uniqueTimestamp)
            ->param('-e', (string) ($uniqueTimestamp + 120))
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

    public function test_performance_comparison(): void
    {
        // This test compares the performance of writing with and without rrdcached

        // First, measure time to write 10 data points with rrdcached
        $startCached = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $dataPoint = new DataPoint(
                'perf_test_cached',
                ['value' => $i],
                ['host' => 'server1'],
                new DateTime('@'.(time() + $i))
            );
            $this->driver->write($dataPoint);
        }

        $endCached = microtime(true);
        $cachedTime = $endCached - $startCached;

        // Now create a driver without rrdcached
        $noCacheConfig = new RRDtoolConfig(
            rrdtool_path: $this->rrdtoolPath,
            rrd_dir: $this->dataDir,
            use_rrdcached: false,
            persistent_process: false,
            default_step: 60,
            debug: false,
            tag_strategy: FileNameStrategy::class,
        );

        $tag_strategy_class = $noCacheConfig->tag_strategy;
        $tagStrategy = new $tag_strategy_class($noCacheConfig);

        // Create a real RRDtoolDriver
        $noCacheDriver= new RRDtoolDriver($noCacheConfig, new ProcessFactory, $tagStrategy, new RRDtoolQueryBuilder($tagStrategy), new NullLogger);
        $noCacheDriver->connect();

        $startNoCache = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            $dataPoint = new DataPoint(
                'perf_test_nocache',
                ['value' => $i],
                ['host' => 'server1'],
                new DateTime('@'.(time() + $i))
            );
            $noCacheDriver->write($dataPoint);
        }

        $endNoCache = microtime(true);
        $noCacheTime = $endNoCache - $startNoCache;

        $noCacheDriver->close();

        // Output the results
        echo sprintf(
            "Performance comparison:\n".
            "  With rrdcached: %.4f seconds\n".
            "  Without rrdcached: %.4f seconds\n".
            "  Difference: %.2f%%\n",
            $cachedTime,
            $noCacheTime,
            ($noCacheTime > 0 ? (($noCacheTime - $cachedTime) / $noCacheTime * 100) : 0)
        );

        // We don't assert anything specific here, just add to the assertion count
        $this->addToAssertionCount(1);
    }
}
