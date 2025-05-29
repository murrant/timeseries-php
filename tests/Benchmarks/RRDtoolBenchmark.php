<?php

namespace TimeSeriesPhp\Tests\Benchmarks;

use TimeSeriesPhp\Drivers\RRDtool\Config\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;

/**
 * Benchmark tests for RRDtool driver.
 *
 * @group benchmark
 */
class RRDtoolBenchmark extends AbstractDriverBenchmark
{
    private RRDtoolConfig $config;

    private string $dataDir;

    private string $rrdtoolPath = 'rrdtool'; // Assumes rrdtool is in PATH

    protected function setUpDriver(): void
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

        // Use a benchmark directory for RRD files
        $this->dataDir = rtrim(sys_get_temp_dir(), '/').'/rrdtool_benchmark/';

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

        try {
            $connected = $this->driver->connect($this->config);
            if (! $connected) {
                $this->markTestSkipped('Could not connect to RRDtool');
            }
        } catch (\Exception $e) {
            $this->markTestSkipped('Could not connect to RRDtool: '.$e->getMessage());
        }
    }

    protected function tearDownDriver(): void
    {
        // Close the driver connection
        if (isset($this->driver) && $this->driver->isConnected()) {
            $this->driver->close();
        }

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

    /**
     * Override the query benchmark to use RRDtool-specific queries.
     */
    public function test_benchmark_query(): void
    {
        if (! $this->driver->isConnected()) {
            $this->markTestSkipped('Driver is not connected');
        }

        // First create an RRD file and write some data to it
        $dataPoint = new \TimeSeriesPhp\Core\Data\DataPoint(
            'benchmark_query',
            ['value' => 42.5],
            ['host' => 'benchmark-host'],
            new \DateTime
        );

        $this->driver->write($dataPoint);

        // Wait a moment for data to be available
        sleep(1);

        $start = microtime(true);

        for ($i = 0; $i < 10; $i++) {
            // Use RRDtool fetch command
            $rrdFile = $this->dataDir.'benchmark_query_benchmark-host.rrd';
            $rawQuery = new \TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolRawQuery(
                'fetch',
                $rrdFile
            );
            $rawQuery->param('AVERAGE', null)
                ->param('--start', '-300')
                ->param('--end', 'now');

            $this->driver->rawQuery($rawQuery);
        }

        $end = microtime(true);
        $duration = $end - $start;
        $queriesPerSecond = 10 / $duration;

        $this->addToAssertionCount(1); // Count this as a passed assertion

        echo sprintf(
            "Query benchmark: 10 queries in %.4f seconds (%.2f queries/sec)\n",
            $duration,
            $queriesPerSecond
        );
    }
}
