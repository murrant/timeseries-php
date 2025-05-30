<?php

require_once __DIR__.'/../vendor/autoload.php';

use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Exceptions\Config\ConfigurationException;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

// Example: Using InfluxDB with timeseries-php library

// Use the token from docker-compose.yml
$token = 'my-token';

// Step 1: Initialize the InfluxDB driver with configuration
echo "Initializing InfluxDB driver...\n";

// Create database configuration
try {
    $influxdbConfig = new \TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig([
        'token' => $token,
        'org' => 'my-org',
        'bucket' => 'example_bucket',
    ]);
} catch (ConfigurationException $e) {
    echo "Failed to configure InfluxDB: {$e->getMessage()}\n";
    exit(1);
}

// Initialize the driver
\TimeSeriesPhp\Core\TSDBFactory::registerDriver('influxdb', InfluxDBDriver::class);

// Connect to the database
try {
    $influxdb = \TimeSeriesPhp\Core\TSDBFactory::create('influxdb', $influxdbConfig);
    echo "Successfully connected to InfluxDB!\n";
} catch (DriverException|ConfigurationException $e) {
    echo "Failed to connect to InfluxDB: {$e->getMessage()}\n";
    exit(1);
}

// Check health
if ($influxdb instanceof InfluxDBDriver) {
    $ping = $influxdb->getHealth();
    var_export($ping);
    if ($ping) {
        echo "Successfully pinged InfluxDB!\n";
    } else {
        echo "Failed to ping InfluxDB.\n";
    }
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
        ],
        $timestamp
    );
}

// Write batch of data points
if ($influxdb->writeBatch($dataPoints)) {
    echo 'Successfully wrote '.count($dataPoints)." data points in batch!\n";
} else {
    echo "Failed to write data points in batch.\n";
}

// Step 3: Query data
echo "\nQuerying data...\n";

// Simple query - get all data from server_metrics
$query = new Query('server_metrics');
$result = $influxdb->query($query);

echo "Simple query results:\n";
echo 'Found '.count($result->getSeries())." data points\n";
print_r($result->getSeries());

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
    ->aggregate('mean', '30m')
    ->limit(10)
    ->orderBy('time', 'DESC');

$complexResult = $influxdb->query($complexQuery);

echo "Complex query results:\n";
echo 'Found '.count($complexResult->getSeries())." data points\n";
print_r($complexResult->getSeries());

// Raw query example
// echo "\nRaw query v1 example:\n";
// $rawResult = $influxdb->rawQuery('SELECT mean(cpu_usage) FROM server_metrics WHERE time > now() - 1h GROUP BY host');
// print_r($rawResult->getSeries());

echo "\nRaw query v2 example:\n";
$rawResult = $influxdb->rawQuery('from(bucket: "example_bucket")
  |> range(start: -1h)
  |> filter(fn: (r) => r._measurement == "server_metrics" and r._field == "cpu_usage")
  |> group(columns: ["host"])
  |> mean()');
print_r($rawResult->getSeries());

// Close the connection
$influxdb->close();
echo "\nConnection closed.\n";

echo "\nExample completed successfully!\n";
