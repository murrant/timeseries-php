<?php

require_once __DIR__.'/../vendor/autoload.php';

use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

// Example: Using RRDtool with timeseries-php library

// Step 1: Initialize the RRDtool driver with configuration
echo "Initializing RRDtool driver...\n";

// Create a directory for RRD files if it doesn't exist
$rrdPath = __DIR__.'/rrd_files';
if (! is_dir($rrdPath)) {
    mkdir($rrdPath, 0755, true);
    echo "Created directory for RRD files: {$rrdPath}\n";
}

// Create database configuration
try {
    $rrdtoolConfig = new \TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig([
        'path' => $rrdPath,                  // Directory where RRD files are stored
        'rrdtool_bin' => '/usr/bin/rrdtool', // Path to the rrdtool binary (adjust as needed)
        'temp_dir' => '/tmp',                // Directory for temporary files
        'default_step' => 300,               // Default step size in seconds (5 minutes)
    ]);
} catch (ConfigurationException $e) {
    echo "Failed to configure RRDtool: {$e->getMessage()}\n";
    exit(1);
}

// Initialize the driver
\TimeSeriesPhp\Core\TSDBFactory::registerDriver('rrdtool', RRDtoolDriver::class);

// Connect to the database
try {
    $rrdtool = \TimeSeriesPhp\Core\TSDBFactory::create('rrdtool', $rrdtoolConfig);
    echo "Successfully connected to RRDtool!\n";
} catch (DriverException|ConfigurationException $e) {
    echo "Failed to connect to RRDtool: {$e->getMessage()}\n";
    exit(1);
}

// Step 2: Create RRD files with predefined structure
echo "\nCreating RRD files...\n";

// Create an RRD file for CPU usage
try {
    $cpuRrdFile = 'server1_cpu.rrd';
    $success = $rrdtool->createRRDFile(
        $cpuRrdFile,                  // File name
        300,                          // Step size (5 minutes)
        [                             // Data sources
            [
                'name' => 'usage',    // DS name
                'type' => 'GAUGE',    // DS type (GAUGE, COUNTER, DERIVE, ABSOLUTE)
                'min' => 0,           // Minimum value
                'max' => 100,         // Maximum value
                'heartbeat' => 600,    // Heartbeat (2 * step)
            ],
        ],
        [                             // Round Robin Archives
            [
                'cf' => 'AVERAGE',    // Consolidation function (AVERAGE, MIN, MAX, LAST)
                'steps' => 1,         // Steps per data point
                'rows' => 2016,        // Number of data points to store (1 week at 5-minute intervals)
            ],
            [
                'cf' => 'AVERAGE',
                'steps' => 12,        // 1 hour (12 * 5 minutes)
                'rows' => 1440,        // 2 months of hourly data
            ],
            [
                'cf' => 'AVERAGE',
                'steps' => 288,       // 1 day (288 * 5 minutes)
                'rows' => 365,         // 1 year of daily data
            ],
            [
                'cf' => 'MIN',        // Minimum values
                'steps' => 1,
                'rows' => 2016,
            ],
            [
                'cf' => 'MAX',        // Maximum values
                'steps' => 1,
                'rows' => 2016,
            ],
        ]
    );

    if ($success) {
        echo "Successfully created RRD file: {$cpuRrdFile}\n";
    } else {
        echo "Failed to create RRD file: {$cpuRrdFile}\n";
    }
} catch (\Exception $e) {
    echo "Error creating CPU RRD file: {$e->getMessage()}\n";
}

// Create an RRD file with multiple data sources
try {
    $metricsRrdFile = 'server1_metrics.rrd';
    $success = $rrdtool->createRRDFile(
        $metricsRrdFile,
        300,
        [
            ['name' => 'cpu', 'type' => 'GAUGE', 'min' => 0, 'max' => 100, 'heartbeat' => 600],
            ['name' => 'memory', 'type' => 'GAUGE', 'min' => 0, 'max' => 100, 'heartbeat' => 600],
            ['name' => 'disk', 'type' => 'GAUGE', 'min' => 0, 'max' => 100, 'heartbeat' => 600],
            ['name' => 'network_in', 'type' => 'COUNTER', 'min' => 0, 'max' => 'U', 'heartbeat' => 600],
            ['name' => 'network_out', 'type' => 'COUNTER', 'min' => 0, 'max' => 'U', 'heartbeat' => 600],
        ],
        [
            ['cf' => 'AVERAGE', 'steps' => 1, 'rows' => 2016],
            ['cf' => 'MIN', 'steps' => 1, 'rows' => 2016],
            ['cf' => 'MAX', 'steps' => 1, 'rows' => 2016],
        ]
    );

    if ($success) {
        echo "Successfully created RRD file: {$metricsRrdFile}\n";
    } else {
        echo "Failed to create RRD file: {$metricsRrdFile}\n";
    }
} catch (\Exception $e) {
    echo "Error creating metrics RRD file: {$e->getMessage()}\n";
}

// Step 3: Write data to RRD files
echo "\nWriting data to RRD files...\n";

// Write a single data point to the CPU RRD file
// Note: The measurement name should match the RRD file name (without .rrd extension)
// The field name should match the data source name
try {
    $dataPoint = new DataPoint(
        'server1_cpu',        // Measurement name (matches RRD file name without .rrd)
        ['usage' => 45.2]     // Field name (matches DS name)
    );

    if ($rrdtool->write($dataPoint)) {
        echo "Successfully wrote data point to server1_cpu.rrd!\n";
    } else {
        echo "Failed to write data point to server1_cpu.rrd.\n";
    }
} catch (\Exception $e) {
    echo "Error writing data point: {$e->getMessage()}\n";
}

// Write a data point with a specific timestamp
try {
    $timestamp = new DateTime;
    $timestamp->modify('-1 hour');

    $dataPoint = new DataPoint(
        'server1_cpu',
        ['usage' => 65.8],
        [],                  // No tags (RRDtool doesn't support tags)
        $timestamp
    );

    if ($rrdtool->write($dataPoint)) {
        echo "Successfully wrote historical data point to server1_cpu.rrd!\n";
    } else {
        echo "Failed to write historical data point to server1_cpu.rrd.\n";
    }
} catch (\Exception $e) {
    echo "Error writing historical data point: {$e->getMessage()}\n";
}

// Write data to the metrics RRD file with multiple data sources
try {
    $dataPoint = new DataPoint(
        'server1_metrics',
        [
            'cpu' => 78.5,
            'memory' => 65.2,
            'disk' => 45.8,
            'network_in' => 1024000,
            'network_out' => 512000,
        ]
    );

    if ($rrdtool->write($dataPoint)) {
        echo "Successfully wrote data point to server1_metrics.rrd!\n";
    } else {
        echo "Failed to write data point to server1_metrics.rrd.\n";
    }
} catch (\Exception $e) {
    echo "Error writing data point to metrics RRD: {$e->getMessage()}\n";
}

// Create multiple data points for batch writing
$dataPoints = [];

// Add some historical data points
for ($i = 1; $i <= 5; $i++) {
    $timestamp = new DateTime;
    $timestamp->modify("-{$i} hour");

    $dataPoints[] = new DataPoint(
        'server1_cpu',
        ['usage' => rand(100, 900) / 10],
        [],
        $timestamp
    );
}

// Write batch of data points
try {
    if ($rrdtool->writeBatch($dataPoints)) {
        echo 'Successfully wrote '.count($dataPoints)." data points in batch!\n";
    } else {
        echo "Failed to write data points in batch.\n";
    }
} catch (\Exception $e) {
    echo "Error writing batch data points: {$e->getMessage()}\n";
}

// Step 4: Query data from RRD files
echo "\nQuerying data from RRD files...\n";

// Simple query - get data from the CPU RRD file
$query = new Query('server1_cpu');
$query->select(['usage']);
try {
    $result = $rrdtool->query($query);

    echo "Simple query results:\n";
    echo 'Found '.count($result->getSeries())." data points\n";
    print_r($result->getSeries());
} catch (\Exception $e) {
    echo "Error executing simple query: {$e->getMessage()}\n";
}

// More complex query with time range and aggregation
echo "\nComplex query with time range and aggregation:\n";

$startTime = new DateTime;
$startTime->modify('-6 hours');
$endTime = new DateTime;

$complexQuery = new Query('server1_cpu');
$complexQuery->select(['usage'])
    ->timeRange($startTime, $endTime)
    ->groupByTime('1h')
    ->avg('usage', 'avg_usage');

try {
    $complexResult = $rrdtool->query($complexQuery);

    echo "Complex query results:\n";
    echo 'Found '.count($complexResult->getSeries())." data points\n";
    print_r($complexResult->getSeries());
} catch (\Exception $e) {
    echo "Error executing complex query: {$e->getMessage()}\n";
}

// Raw query examples using RRDtool commands
echo "\nRaw query examples:\n";
try {
    // Fetch data from an RRD file
    $fetchResult = $rrdtool->rawQuery('fetch server1_cpu.rrd AVERAGE --start -1d --end now');
    echo "Fetch query results:\n";
    print_r($fetchResult->getSeries());

    // More complex query using xport
    $xportResult = $rrdtool->rawQuery('xport --start -1d --end now DEF:cpu=server1_cpu.rrd:usage:AVERAGE XPORT:cpu:"CPU Usage"');
    echo "Xport query results:\n";
    print_r($xportResult->getSeries());
} catch (\Exception $e) {
    echo "Error executing raw query: {$e->getMessage()}\n";
}

// Step 5: RRD file management
echo "\nRRD file management examples:\n";

// Get information about an RRD file
try {
    $infoResult = $rrdtool->rawQuery('info server1_cpu.rrd');
    echo "RRD file info:\n";
    print_r($infoResult->getSeries());
} catch (\Exception $e) {
    echo "Error getting RRD file info: {$e->getMessage()}\n";
}

// Dump an RRD file to XML (for backup or migration)
try {
    $dumpFile = $rrdPath.'/server1_cpu.xml';
    $dumpResult = $rrdtool->rawQuery("dump server1_cpu.rrd > {$dumpFile}");
    echo "RRD file dumped to XML: {$dumpFile}\n";
} catch (\Exception $e) {
    echo "Error dumping RRD file: {$e->getMessage()}\n";
}

// Close the connection
$rrdtool->close();
echo "\nConnection closed.\n";

echo "\nExample completed successfully!\n";
