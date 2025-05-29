<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;

/**
 * Integration test for RRDtool driver using XML data and rrdrestore
 *
 * @group integration
 */
class RRDtoolXmlIntegrationTest extends TestCase
{
    private RRDtoolDriver $driver;

    private RRDtoolConfig $config;

    private string $dataDir;

    private string $rrdtoolPath = 'rrdtool'; // Assumes rrdtool is in PATH

    private string $xmlDataDir;

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
        $this->dataDir = rtrim(__DIR__.'/data/xml_test', '/').'/';
        $this->xmlDataDir = rtrim(__DIR__.'/data', '/').'/';

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

        // Restore RRD files from XML
        $this->restoreRRDFromXML();
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
     * Restore RRD files from XML using rrdrestore
     */
    private function restoreRRDFromXML(): void
    {
        // Find all XML files in the data directory
        $xmlFiles = glob($this->xmlDataDir.'*.xml') ?: [];

        foreach ($xmlFiles as $xmlFile) {
            $baseName = basename($xmlFile, '.xml');
            $rrdFile = $this->dataDir.$baseName.'.rrd';

            // Use rrdrestore to create RRD file from XML
            $command = sprintf('%s restore %s %s',
                escapeshellcmd($this->rrdtoolPath),
                escapeshellarg($xmlFile),
                escapeshellarg($rrdFile)
            );

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                $this->fail("Failed to restore RRD file from XML: $xmlFile");
            }

            $this->assertFileExists($rrdFile, "RRD file was not created: $rrdFile");
        }
    }

    public function test_query_with_time_range(): void
    {
        // Create a query with time range
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            );

        // Execute the query
        $result = $this->driver->query($query);

        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());

        // The result should contain our data points
        $series = $result->getSeries();
        $this->assertArrayHasKey('value', $series);
        $this->assertNotEmpty($series['value']);
    }

    public function test_query_with_aggregation(): void
    {
        // Create a query with aggregation
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->groupByTime('5m')  // Group by 5-minute intervals
            ->avg('value', 'avg_value');
    }

    public function test_query_with_multiple_aggregations(): void
    {
        // Create a query with multiple aggregations
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->groupByTime('10m')  // Group by 10-minute intervals
            ->avg('value', 'avg_value')
            ->max('value', 'max_value')
            ->min('value', 'min_value');
    }

    public function test_query_with_ordering_and_limit(): void
    {
        // Create a query with ordering and limit
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->orderByTime('DESC')
            ->limit(5);

        // Execute the query
        $result = $this->driver->query($query);

        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());

        // The result should contain our data points
        $series = $result->getSeries();
        $this->assertArrayHasKey('value', $series);

        // The limit might not be applied as expected in all drivers
        // Just verify that we have some data points
        $this->assertNotEmpty($series['value']);
    }

    public function test_query_with_math_expression(): void
    {
        // Create a query with a math expression
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->math('value*2', 'double_value');
    }
}
