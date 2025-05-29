<?php

require_once __DIR__.'/../vendor/autoload.php';

use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

// Example: Using Graphite with timeseries-php library

// Step 1: Initialize the Graphite driver with configuration
echo "Initializing Graphite driver...\n";

// Create database configuration
try {
    $graphiteConfig = new \TimeSeriesPhp\Drivers\Graphite\GraphiteConfig([
        'host' => 'localhost',
        'port' => 2003,         // Default port for Carbon daemon
        'protocol' => 'tcp',    // 'tcp' or 'udp'
        'timeout' => 10,
        'prefix' => 'example.', // Optional prefix for all metrics
    ]);
} catch (ConfigurationException $e) {
    echo "Failed to configure Graphite: {$e->getMessage()}\n";
    exit(1);
}

// Initialize the driver
\TimeSeriesPhp\Core\TSDBFactory::registerDriver('graphite', GraphiteDriver::class);

// Connect to the database
try {
    $graphite = \TimeSeriesPhp\Core\TSDBFactory::create('graphite', $graphiteConfig);
    echo "Successfully connected to Graphite!\n";
} catch (DriverException|ConfigurationException $e) {
    echo "Failed to connect to Graphite: {$e->getMessage()}\n";
    exit(1);
}

// Step 2: Create and save data points
echo "\nSaving data points...\n";

// Create a single data point
// In Graphite, the measurement name often uses dot notation
$dataPoint = new DataPoint(
    'servers.server1.cpu_usage',    // measurement name with dot notation
    ['value' => 45.2]               // fields (actual data)
);

// Write the data point
try {
    if ($graphite->write($dataPoint)) {
        echo "Successfully wrote data point!\n";
    } else {
        echo "Failed to write data point.\n";
    }
} catch (\Exception $e) {
    echo "Error writing data point: {$e->getMessage()}\n";
}

// Create a data point using tags (for newer versions of Graphite)
$taggedDataPoint = new DataPoint(
    'cpu_usage',                       // measurement name
    ['value' => 78.3],                 // fields (actual data)
    ['host' => 'server1', 'region' => 'us-west'] // tags
);

// Write the tagged data point
try {
    if ($graphite->write($taggedDataPoint)) {
        echo "Successfully wrote tagged data point!\n";
    } else {
        echo "Failed to write tagged data point.\n";
    }
} catch (\Exception $e) {
    echo "Error writing tagged data point: {$e->getMessage()}\n";
}

// Create multiple data points for batch writing
$dataPoints = [];

// Add some historical data points
for ($i = 1; $i <= 5; $i++) {
    $timestamp = new DateTime;
    $timestamp->modify("-{$i} hour");

    // Using dot notation for metric names
    $dataPoints[] = new DataPoint(
        "servers.server{$i}.metrics",
        [
            'cpu_usage' => rand(100, 900) / 10,
            'memory' => rand(20, 95),
            'disk_usage' => rand(30, 85),
        ],
        [],  // No tags for this example
        $timestamp
    );
}

// Write batch of data points
try {
    if ($graphite->writeBatch($dataPoints)) {
        echo 'Successfully wrote '.count($dataPoints)." data points in batch!\n";
    } else {
        echo "Failed to write data points in batch.\n";
    }
} catch (\Exception $e) {
    echo "Error writing batch data points: {$e->getMessage()}\n";
}

// Step 3: Query data
echo "\nQuerying data...\n";

// Simple query - get data from a specific metric
$query = new Query('servers.server1.cpu_usage');
try {
    $result = $graphite->query($query);

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

$complexQuery = new Query('servers.server1.cpu_usage');
$complexQuery->select(['value'])
    ->timeRange($startTime, $endTime)
    ->groupByTime('30m')
    ->avg('value', 'avg_value');

try {
    $complexResult = $graphite->query($complexQuery);

    echo "Complex query results:\n";
    echo 'Found '.count($complexResult->getSeries())." data points\n";
    print_r($complexResult->getSeries());
} catch (\Exception $e) {
    echo "Error executing complex query: {$e->getMessage()}\n";
}

// Raw query example using Graphite's function-based query language
echo "\nRaw query examples:\n";
try {
    // Simple function-based query
    $rawResult = $graphite->rawQuery('averageSeries(servers.server1.cpu_usage)');
    echo "Simple function query results:\n";
    print_r($rawResult->getSeries());

    // More complex function-based query
    $complexRawResult = $graphite->rawQuery('movingAverage(summarize(servers.server1.cpu_usage, "5min", "avg"), 10)');
    echo "Complex function query results:\n";
    print_r($complexRawResult->getSeries());
} catch (\Exception $e) {
    echo "Error executing raw query: {$e->getMessage()}\n";
}

// Example of using different protocols
echo "\nProtocol selection examples:\n";

// TCP protocol (already configured above)
echo "Using TCP protocol (reliable but potentially slower):\n";
try {
    $tcpDataPoint = new DataPoint('servers.server1.protocol.tcp', ['value' => 1]);
    $success = $graphite->write($tcpDataPoint);
    echo $success ? "Successfully wrote TCP data point.\n" : "Failed to write TCP data point.\n";
} catch (\Exception $e) {
    echo "Error with TCP protocol: {$e->getMessage()}\n";
}

// UDP protocol example (create a new configuration and driver)
try {
    $udpConfig = new \TimeSeriesPhp\Drivers\Graphite\GraphiteConfig([
        'host' => 'localhost',
        'port' => 2003,
        'protocol' => 'udp',    // Using UDP for faster, non-blocking writes
        'prefix' => 'example.',
    ]);

    $udpGraphite = \TimeSeriesPhp\Core\TSDBFactory::create('graphite', $udpConfig);

    echo "Using UDP protocol (faster but less reliable):\n";
    $udpDataPoint = new DataPoint('servers.server1.protocol.udp', ['value' => 1]);
    $success = $udpGraphite->write($udpDataPoint);
    echo $success ? "Successfully wrote UDP data point.\n" : "Failed to write UDP data point.\n";

    $udpGraphite->close();
} catch (\Exception $e) {
    echo "Error with UDP protocol: {$e->getMessage()}\n";
}

// Close the connection
$graphite->close();
echo "\nConnection closed.\n";

echo "\nExample completed successfully!\n";
