<?php

require_once __DIR__.'/../vendor/autoload.php';

use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

// Example: Using Prometheus with timeseries-php library

// Step 1: Initialize the Prometheus driver with configuration
echo "Initializing Prometheus driver...\n";

// Create database configuration
try {
    $prometheusConfig = new \TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig([
        'url' => 'http://localhost:9090',
        // Uncomment and set these if your Prometheus server requires authentication
        // 'username' => 'your-username',
        // 'password' => 'your-password',
        'timeout' => 10,
    ]);
} catch (ConfigurationException $e) {
    echo "Failed to configure Prometheus: {$e->getMessage()}\n";
    exit(1);
}

// Initialize the driver
\TimeSeriesPhp\Core\TSDBFactory::registerDriver('prometheus', PrometheusDriver::class);

// Connect to the database
try {
    $prometheus = \TimeSeriesPhp\Core\TSDBFactory::create('prometheus', $prometheusConfig);
    echo "Successfully connected to Prometheus!\n";
} catch (DriverException|ConfigurationException $e) {
    echo "Failed to connect to Prometheus: {$e->getMessage()}\n";
    exit(1);
}

// Check health
if ($prometheus instanceof PrometheusDriver) {
    $health = $prometheus->getHealth();
    var_export($health);
    if ($health) {
        echo "Successfully checked Prometheus health!\n";
    } else {
        echo "Failed to check Prometheus health.\n";
    }
}

// Step 2: Create and save data points
echo "\nSaving data points...\n";

// Note: Prometheus is primarily a pull-based system, but TimeSeriesPhp provides a way to push metrics
// via the Prometheus Pushgateway. Make sure you have a Pushgateway running.

// Create a single data point
// Note: In Prometheus, the 'job' label is important
$dataPoint = new DataPoint(
    'server_metrics',                      // measurement name
    ['cpu_usage' => 45.2, 'memory' => 78], // fields (actual data)
    [
        'host' => 'web-01', 
        'region' => 'us-west',
        'job' => 'node'                    // job label is important for Prometheus
    ] 
);

// Write the data point
try {
    if ($prometheus->write($dataPoint)) {
        echo "Successfully wrote data point!\n";
    } else {
        echo "Failed to write data point.\n";
    }
} catch (\Exception $e) {
    echo "Error writing data point: {$e->getMessage()}\n";
}

// Create multiple data points for batch writing
$dataPoints = [];

// Add some historical data points
for ($i = 1; $i <= 5; $i++) {
    $timestamp = new DateTime;
    $timestamp->modify("-{$i} hour");

    $dataPoints[] = new DataPoint(
        'server_metrics',
        [
            'cpu_usage' => rand(100, 900) / 10,
            'memory' => rand(20, 95),
            'disk_usage' => rand(30, 85),
        ],
        [
            'host' => 'web-'.str_pad($i % 3 + 1, 2, '0', STR_PAD_LEFT),
            'region' => ($i % 2 == 0) ? 'us-east' : 'us-west',
            'environment' => ($i % 3 == 0) ? 'production' : 'staging',
            'job' => 'node'
        ],
        $timestamp
    );
}

// Write batch of data points
try {
    if ($prometheus->writeBatch($dataPoints)) {
        echo 'Successfully wrote '.count($dataPoints)." data points in batch!\n";
    } else {
        echo "Failed to write data points in batch.\n";
    }
} catch (\Exception $e) {
    echo "Error writing batch data points: {$e->getMessage()}\n";
}

// Step 3: Query data
echo "\nQuerying data...\n";

// Simple query - get all data from server_metrics
$query = new Query('server_metrics');
try {
    $result = $prometheus->query($query);

    echo "Simple query results:\n";
    echo 'Found '.count($result->getSeries())." data points\n";
    print_r($result->getSeries());
} catch (\Exception $e) {
    echo "Error executing simple query: {$e->getMessage()}\n";
}

// More complex query with filtering, time range, and aggregation
echo "\nComplex query with filtering and time range:\n";

$startTime = new DateTime;
$startTime->modify('-6 hours');
$endTime = new DateTime;

$complexQuery = new Query('server_metrics');
$complexQuery->select(['mean(cpu_usage) as avg_cpu', 'max(memory) as max_memory'])
    ->where('host', 'web-01')
    ->timeRange($startTime, $endTime)
    ->groupBy(['host', 'region'])
    ->groupByTime('30m')
    ->limit(10)
    ->orderBy('time', 'DESC');

try {
    $complexResult = $prometheus->query($complexQuery);

    echo "Complex query results:\n";
    echo 'Found '.count($complexResult->getSeries())." data points\n";
    print_r($complexResult->getSeries());
} catch (\Exception $e) {
    echo "Error executing complex query: {$e->getMessage()}\n";
}

// Raw query example using PromQL
echo "\nRaw query (PromQL) example:\n";
try {
    // Simple PromQL query
    $rawResult = $prometheus->rawQuery('server_metrics{host="web-01"}[1h]');
    echo "Simple PromQL query results:\n";
    print_r($rawResult->getSeries());

    // More complex PromQL query
    $complexRawResult = $prometheus->rawQuery('avg_over_time(server_metrics{host="web-01"}[5m])[1h:5m]');
    echo "Complex PromQL query results:\n";
    print_r($complexRawResult->getSeries());
} catch (\Exception $e) {
    echo "Error executing raw query: {$e->getMessage()}\n";
}

// Close the connection
$prometheus->close();
echo "\nConnection closed.\n";

echo "\nExample completed successfully!\n";
