<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;

/**
 * Integration test for RRDtool driver using the Query interface
 *
 * @group integration
 */
class QueryInterfaceTest extends TestCase
{
    private RRDtoolDriver $driver;

    private RRDtoolConfig $config;

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
        $this->dataDir = rtrim(__DIR__.'/data/query_test', '/').'/';

        // Ensure the directory exists and is writable
        if (! is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0777, true);
        }

        if (! is_writable($this->dataDir)) {
            $this->markTestSkipped('Data directory is not writable: '.$this->dataDir);
        }

        // Create a real RRDtoolConfig
        $this->config = new RRDtoolConfig([
            'rrd_dir' => $this->dataDir,
            'rrdtool_path' => $this->rrdtoolPath,
            'use_rrdcached' => false,
            'default_step' => 60, // Use a smaller step for testing
            'tag_strategy' => FileNameStrategy::class,
            'debug' => false,
            'persistent_process' => false,
            'graph_output' => 'file',
            'default_archives' => [
                'RRA:AVERAGE:0.5:1:1440',  // 1min for 1 day
                'RRA:MAX:0.5:1:1440',      // 1min max for 1 day
                'RRA:MIN:0.5:1:1440',      // 1min min for 1 day
            ],
        ]);

        // Create a real RRDtoolDriver
        $this->driver = new RRDtoolDriver;
        $this->driver->connect($this->config);

        // Populate test data
        $this->populateTestData();
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
    }

    /**
     * Populate test data for query tests
     */
    private function populateTestData(): void
    {
        $now = new DateTime();
        $startTime = $now->getTimestamp() - 3600; // 1 hour ago

        // Create CPU usage data for multiple servers
        $servers = ['server1', 'server2', 'server3'];
        $regions = ['us-east', 'us-west', 'eu-central'];
        
        foreach ($servers as $i => $server) {
            $region = $regions[$i % count($regions)];
            
            // Create data points at 1-minute intervals
            for ($j = 0; $j < 60; $j++) {
                $timestamp = $startTime + ($j * 60);
                $time = new DateTime('@'.$timestamp);
                
                // Create some variation in the data
                $cpuValue = 20 + ($i * 10) + (sin($j / 10) * 15);
                $memValue = 2048 + ($i * 512) + (cos($j / 10) * 256);
                
                // CPU usage data point
                $cpuPoint = new DataPoint(
                    'cpu_usage',
                    ['value' => $cpuValue],
                    [
                        'host' => $server,
                        'region' => $region,
                    ],
                    $time
                );
                
                // Memory usage data point
                $memPoint = new DataPoint(
                    'memory_usage',
                    ['value' => $memValue],
                    [
                        'host' => $server,
                        'region' => $region,
                    ],
                    $time
                );
                
                $this->driver->write($cpuPoint);
                $this->driver->write($memPoint);
            }
        }
    }

    public function test_simple_query(): void
    {
        // Create a simple query to get CPU usage for server1
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1');
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());
        
        // The result should contain our data points
        $series = $result->getSeries();
        $this->assertArrayHasKey('value', $series);
    }

    public function test_query_with_time_range(): void
    {
        $now = new DateTime();
        $startTime = new DateTime('@'.($now->getTimestamp() - 3600)); // 1 hour ago
        $endTime = new DateTime('@'.($now->getTimestamp() - 1800)); // 30 minutes ago
        
        // Create a query with time range
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
              ->timeRange($startTime, $endTime);
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());
        
        // The result should contain our data points within the time range
        $series = $result->getSeries();
        $this->assertArrayHasKey('value', $series);
        
        // Check that all timestamps are within the specified range
        foreach ($result->getTimestamps() as $timestamp) {
            $time = new DateTime('@'.$timestamp);
            $this->assertGreaterThanOrEqual($startTime, $time);
            $this->assertLessThanOrEqual($endTime, $time);
        }
    }

    public function test_query_with_aggregation(): void
    {
        // Create a query with aggregation
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
              ->groupByTime('5m')  // Group by 5-minute intervals
              ->avg('value', 'avg_value');
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());
        
        // The result should contain our aggregated data
        $series = $result->getSeries();
        $this->assertArrayHasKey('avg_value', $series);
    }

    public function test_query_with_multiple_conditions(): void
    {
        // Create a query with multiple conditions
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
              ->where('region', '=', 'us-east');
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        
        // The result should contain our data points
        $series = $result->getSeries();
        if (!empty($series)) {
            $this->assertArrayHasKey('value', $series);
        }
    }

    public function test_query_with_ordering_and_limit(): void
    {
        // Create a query with ordering and limit
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
              ->orderByTime('DESC')
              ->limit(10);
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());
        
        // The result should contain our data points
        $series = $result->getSeries();
        $this->assertArrayHasKey('value', $series);
        
        // Check that we have at most 10 data points
        $this->assertLessThanOrEqual(10, count($result->getTimestamps()));
        
        // Check that timestamps are in descending order
        $timestamps = $result->getTimestamps();
        $sortedTimestamps = $timestamps;
        rsort($sortedTimestamps);
        $this->assertEquals($sortedTimestamps, $timestamps);
    }
}
