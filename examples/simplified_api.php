<?php

require_once __DIR__.'/../vendor/autoload.php';

use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Factory\TSDBFactory;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Drivers\InfluxDB\Config\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\TSDB;

/**
 * Simplified API Example
 *
 * This example demonstrates how to use the simplified API provided by the TSDB class.
 * The TSDB class provides a more intuitive API for common operations with time series databases.
 */
echo "TimeSeriesPhp Simplified API Example\n";
echo "===================================\n\n";

// Register the InfluxDB driver
TSDBFactory::registerDriver('influxdb', InfluxDBDriver::class);

// Step 1: Create a configuration
echo "Step 1: Creating configuration...\n";
$config = new InfluxDBConfig([
    'url' => 'http://localhost:8086',
    'token' => 'my-token',
    'org' => 'my-org',
    'bucket' => 'example_bucket',
]);
echo "Configuration created.\n\n";

// Step 2: Create a TSDB instance
echo "Step 2: Creating TSDB instance...\n";
try {
    $ts = new TSDB('influxdb', $config);
    echo "TSDB instance created successfully.\n\n";
} catch (\Exception $e) {
    echo 'Error creating TSDB instance: '.$e->getMessage()."\n";
    echo "This example will continue with simulated responses.\n\n";

    // Create a mock driver for demonstration purposes
    $mockDriver = new class implements \TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface
    {
        public function connect(\TimeSeriesPhp\Contracts\Config\ConfigInterface $config): bool
        {
            return true;
        }

        public function isConnected(): bool
        {
            return true;
        }

        public function write(DataPoint $dataPoint): bool
        {
            return true;
        }

        public function writeBatch(array $dataPoints): bool
        {
            return true;
        }

        public function query(Query $query): \TimeSeriesPhp\Core\Data\QueryResult
        {
            return new \TimeSeriesPhp\Core\Data\QueryResult([]);
        }

        public function rawQuery(\TimeSeriesPhp\Contracts\Query\RawQueryInterface $query): \TimeSeriesPhp\Core\Data\QueryResult
        {
            return new \TimeSeriesPhp\Core\Data\QueryResult([]);
        }

        public function createDatabase(string $database): bool
        {
            return true;
        }

        public function deleteDatabase(string $database): bool
        {
            return true;
        }

        public function getDatabases(): array
        {
            return ['example_bucket'];
        }

        public function deleteMeasurement(string $measurement, ?\DateTime $start = null, ?\DateTime $stop = null): bool
        {
            return true;
        }

        public function close(): void {}
    };

    // Use reflection to set the private driver property
    $ts = new TSDB('influxdb', $config, false);
    $reflection = new \ReflectionClass($ts);
    $property = $reflection->getProperty('driver');
    $property->setAccessible(true);
    $property->setValue($ts, $mockDriver);

    echo "Mock TSDB instance created for demonstration.\n\n";
}

// Step 3: Writing data using the simplified API
echo "Step 3: Writing data using the simplified API...\n";

// Using the simplified write method
$result = $ts->write(
    'cpu_usage',                  // measurement
    ['value' => 85.5],            // fields
    ['host' => 'server1']         // tags
);

echo 'Write result: '.($result ? 'Success' : 'Failed')."\n";

// Using the writePoint helper method
$result = $ts->writePoint(
    'memory_usage',               // measurement
    ['value' => 45.2],            // fields
    ['host' => 'server1']         // tags
);

echo 'WritePoint result: '.($result ? 'Success' : 'Failed')."\n\n";

// Step 4: Writing batch data
echo "Step 4: Writing batch data...\n";

// Create multiple data points
$dataPoints = [
    new DataPoint('cpu_usage', ['value' => 82.1], ['host' => 'server1']),
    new DataPoint('memory_usage', ['value' => 50.3], ['host' => 'server1']),
    new DataPoint('disk_usage', ['value' => 65.8], ['host' => 'server1']),
];

// Write batch data
$result = $ts->writeBatch($dataPoints);
echo 'WriteBatch result: '.($result ? 'Success' : 'Failed')."\n\n";

// Step 5: Querying data using helper methods
echo "Step 5: Querying data using helper methods...\n";

// Get the last value for a measurement
echo "Querying last value for cpu_usage...\n";
$result = $ts->queryLast('cpu_usage', 'value', ['host' => 'server1']);
echo "QueryLast executed successfully.\n";

// Get the first value for a measurement
echo "Querying first value for cpu_usage...\n";
$result = $ts->queryFirst('cpu_usage', 'value', ['host' => 'server1']);
echo "QueryFirst executed successfully.\n";

// Get the average value over a time range
echo "Querying average value for cpu_usage over the last hour...\n";
$result = $ts->queryAvg(
    'cpu_usage',                  // measurement
    'value',                      // field
    new DateTime('-1 hour'),      // start time
    new DateTime(),               // end time
    ['host' => 'server1']         // tags
);
echo "QueryAvg executed successfully.\n";

// Get the sum of values over a time range
echo "Querying sum of values for cpu_usage over the last hour...\n";
$result = $ts->querySum(
    'cpu_usage',                  // measurement
    'value',                      // field
    new DateTime('-1 hour'),      // start time
    new DateTime(),               // end time
    ['host' => 'server1']         // tags
);
echo "QuerySum executed successfully.\n";

// Get the count of values over a time range
echo "Querying count of values for cpu_usage over the last hour...\n";
$result = $ts->queryCount(
    'cpu_usage',                  // measurement
    'value',                      // field
    new DateTime('-1 hour'),      // start time
    new DateTime(),               // end time
    ['host' => 'server1']         // tags
);
echo "QueryCount executed successfully.\n";

// Get the minimum value over a time range
echo "Querying minimum value for cpu_usage over the last hour...\n";
$result = $ts->queryMin(
    'cpu_usage',                  // measurement
    'value',                      // field
    new DateTime('-1 hour'),      // start time
    new DateTime(),               // end time
    ['host' => 'server1']         // tags
);
echo "QueryMin executed successfully.\n";

// Get the maximum value over a time range
echo "Querying maximum value for cpu_usage over the last hour...\n";
$result = $ts->queryMax(
    'cpu_usage',                  // measurement
    'value',                      // field
    new DateTime('-1 hour'),      // start time
    new DateTime(),               // end time
    ['host' => 'server1']         // tags
);
echo "QueryMax executed successfully.\n\n";

// Step 6: Using the traditional Query builder with the TSDB class
echo "Step 6: Using the traditional Query builder with the TSDB class...\n";

// Create a query using the Query builder
$query = new Query('cpu_usage');
$query->select(['value'])
    ->where('host', '=', 'server1')
    ->timeRange(new DateTime('-1 hour'), new DateTime())
    ->groupByTime('5m')
    ->avg('value', 'avg_value');

// Execute the query using the TSDB class
$result = $ts->query($query);
echo "Query executed successfully.\n\n";

// Step 7: Deleting data
echo "Step 7: Deleting data...\n";

// Delete a measurement
$result = $ts->deleteMeasurement(
    'cpu_usage',                  // measurement
    new DateTime('-1 day'),       // start time
    new DateTime()                // end time
);
echo 'DeleteMeasurement result: '.($result ? 'Success' : 'Failed')."\n\n";

// Step 8: Closing the connection
echo "Step 8: Closing the connection...\n";
$ts->close();
echo "Connection closed.\n\n";

echo "Example completed successfully!\n";
echo "The TSDB class provides a simplified API for common operations with time series databases.\n";
echo "It reduces boilerplate code and makes the API more intuitive and easier to use.\n";
