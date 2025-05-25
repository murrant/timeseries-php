<?php

require_once __DIR__ . '/../vendor/autoload.php';

use TimeSeriesPhp\Config\ConfigFactory;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;

// Example: Using InfluxDB with timeseries-php library

// Step 1: Initialize the InfluxDB driver with configuration
echo "Initializing InfluxDB driver...\n";

// Create database configuration
$dbConfig = ConfigFactory::create('database', [
    'host' => 'localhost',
    'port' => 8086,
    'database' => 'example_db',
    'username' => 'admin',
    'password' => 'password',
    'timeout' => 10,
    'retry_attempts' => 3
]);

// Create connection configuration
$connConfig = ConfigFactory::create('connection', [
    'pool_size' => 5,
    'max_idle_time' => 60,
    'connection_lifetime' => 1800,
    'reconnect_on_failure' => true
]);

// Initialize the driver
$influxdb = new InfluxDBDriver();

// Connect to the database
if ($influxdb->connect($dbConfig)) {
    echo "Successfully connected to InfluxDB!\n";
} else {
    echo "Failed to connect to InfluxDB.\n";
    exit(1);
}

// Step 2: Create and save data points
echo "\nSaving data points...\n";

// Create a single data point
$dataPoint = new DataPoint(
    'server_metrics',                      // measurement name
    ['cpu_usage' => 45.2, 'memory' => 78], // fields (actual data)
    ['host' => 'web-01', 'region' => 'us-west'] // tags (metadata)
);

// Write the data point
if ($influxdb->write($dataPoint)) {
    echo "Successfully wrote data point!\n";
} else {
    echo "Failed to write data point.\n";
}

// Create multiple data points for batch writing
$dataPoints = [];

// Add some historical data points
for ($i = 1; $i <= 5; $i++) {
    $timestamp = new DateTime();
    $timestamp->modify("-{$i} hour");
    
    $dataPoints[] = new DataPoint(
        'server_metrics',
        [
            'cpu_usage' => rand(10, 90),
            'memory' => rand(20, 95),
            'disk_usage' => rand(30, 85)
        ],
        [
            'host' => 'web-' . str_pad($i % 3 + 1, 2, '0', STR_PAD_LEFT),
            'region' => ($i % 2 == 0) ? 'us-east' : 'us-west',
            'environment' => ($i % 3 == 0) ? 'production' : 'staging'
        ],
        $timestamp
    );
}

// Write batch of data points
if ($influxdb->writeBatch($dataPoints)) {
    echo "Successfully wrote " . count($dataPoints) . " data points in batch!\n";
} else {
    echo "Failed to write data points in batch.\n";
}

// Step 3: Query data
echo "\nQuerying data...\n";

// Simple query - get all data from server_metrics
$query = new Query('server_metrics');
$result = $influxdb->query($query);
var_dump($result); exit;

echo "Simple query results:\n";
echo "Found " . count($result->getSeries()) . " data points\n";
print_r($result->getSeries());

// More complex query with filtering, time range, and aggregation
echo "\nComplex query with filtering and time range:\n";

$startTime = new DateTime();
$startTime->modify('-6 hours');
$endTime = new DateTime();

$complexQuery = new Query('server_metrics');
$complexQuery->select(['mean(cpu_usage) as avg_cpu', 'max(memory) as max_memory'])
    ->where('host', 'web-01')
    ->timeRange($startTime, $endTime)
    ->groupBy(['host', 'region'])
    ->aggregate('mean', '30m')
    ->limit(10)
    ->orderBy('time', 'DESC');

$complexResult = $influxdb->query($complexQuery);

echo "Complex query results:\n";
echo "Found " . count($complexResult->getSeries()) . " data points\n";
print_r($complexResult->getSeries());

// Raw query example
echo "\nRaw query example:\n";
$rawResult = $influxdb->rawQuery('SELECT mean(cpu_usage) FROM server_metrics WHERE time > now() - 1h GROUP BY host');
print_r($rawResult->getSeries());

// Close the connection
$influxdb->close();
echo "\nConnection closed.\n";

echo "\nExample completed successfully!\n";
