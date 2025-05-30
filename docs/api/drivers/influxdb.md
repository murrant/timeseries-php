# InfluxDB Driver for TimeSeriesPhp

This document provides detailed information about the InfluxDB driver in TimeSeriesPhp.

## Introduction

The InfluxDB driver allows TimeSeriesPhp to connect to and interact with InfluxDB time series databases. It supports both InfluxDB 1.x and 2.x versions, with appropriate configuration.

## Configuration

The InfluxDB driver is configured using the `InfluxDBConfig` class.

### InfluxDB 2.x Configuration

```php
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;

$config = new InfluxDBConfig([
    'url' => 'http://localhost:8086',
    'token' => 'your-token',
    'org' => 'your-org',
    'bucket' => 'your-bucket',
    'precision' => 'ns', // Optional, defaults to 'ns'
]);
```

### InfluxDB 1.x Configuration

```php
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;

$config = new InfluxDBConfig([
    'url' => 'http://localhost:8086',
    'username' => 'your-username',
    'password' => 'your-password',
    'database' => 'your-database',
    'precision' => 'ns', // Optional, defaults to 'ns'
]);
```

### Configuration Options

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `url` | string | Yes | - | The URL of the InfluxDB server |
| `token` | string | Yes (for 2.x) | - | The API token for InfluxDB 2.x |
| `org` | string | Yes (for 2.x) | - | The organization name for InfluxDB 2.x |
| `bucket` | string | Yes (for 2.x) | - | The bucket name for InfluxDB 2.x |
| `username` | string | Yes (for 1.x) | - | The username for InfluxDB 1.x |
| `password` | string | Yes (for 1.x) | - | The password for InfluxDB 1.x |
| `database` | string | Yes (for 1.x) | - | The database name for InfluxDB 1.x |
| `precision` | string | No | 'ns' | The timestamp precision ('ns', 'us', 'ms', 's') |
| `timeout` | int | No | 10 | The connection timeout in seconds |
| `verify_ssl` | bool | No | true | Whether to verify SSL certificates |
| `debug` | bool | No | false | Whether to enable debug mode |

## Creating a Driver Instance

```php
use TimeSeriesPhp\Core\DriverManager;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;

// Create configuration
$config = new InfluxDBConfig([
    'url' => 'http://localhost:8086',
    'token' => 'your-token',
    'org' => 'your-org',
    'bucket' => 'your-bucket',
]);

// Create driver instance
$db = DriverManager::create('influxdb', $config);
```

## Writing Data

### Writing a Single Data Point

```php
use TimeSeriesPhp\Core\DataPoint;

$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1', 'region' => 'us-west']
);

$success = $db->write($dataPoint);
```

### Writing Multiple Data Points

```php
use TimeSeriesPhp\Core\DataPoint;

$dataPoints = [
    new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']),
    new DataPoint('memory_usage', ['value' => 75.2], ['host' => 'server1']),
    new DataPoint('disk_usage', ['value' => 60.8], ['host' => 'server1']),
];

$success = $db->writeBatch($dataPoints);
```

## Querying Data

### Using the Query Builder

```php
use TimeSeriesPhp\Core\Query;

$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime())
      ->groupByTime('5m')
      ->avg('value', 'avg_value');

$result = $db->query($query);

foreach ($result as $row) {
    echo "Time: {$row['time']}, Average: {$row['avg_value']}\n";
}
```

### Using Raw Queries

#### InfluxQL (1.x)

```php
$result = $db->rawQuery('SELECT mean("value") FROM "cpu_usage" WHERE "host" = \'server1\' AND time > now() - 1h GROUP BY time(5m)');
```

#### Flux (2.x)

```php
$flux = 'from(bucket: "your-bucket")
  |> range(start: -1h)
  |> filter(fn: (r) => r._measurement == "cpu_usage" and r.host == "server1")
  |> aggregateWindow(every: 5m, fn: mean)';

$result = $db->rawQuery($flux);
```

## Database Management

### Creating a Database/Bucket

```php
// For InfluxDB 1.x, this creates a database
// For InfluxDB 2.x, this creates a bucket
$success = $db->createDatabase('new_database');
```

### Deleting a Database/Bucket

```php
// For InfluxDB 1.x, this deletes a database
// For InfluxDB 2.x, this deletes a bucket
$success = $db->deleteDatabase('old_database');
```

### Listing Databases/Buckets

```php
// For InfluxDB 1.x, this lists databases
// For InfluxDB 2.x, this lists buckets
$databases = $db->getDatabases();
```

### Deleting a Measurement

```php
// Delete an entire measurement
$success = $db->deleteMeasurement('cpu_usage');

// Delete data within a specific time range
$start = new DateTime('-1 day');
$end = new DateTime();
$success = $db->deleteMeasurement('cpu_usage', $start, $end);
```

### Listing Measurements

```php
$measurements = $db->getMeasurements();
```

## Retention Policies

### Creating a Retention Policy

```php
// Create a retention policy that keeps data for 30 days
$success = $db->createRetentionPolicy('month', '30d', 1, true);
```

### Deleting a Retention Policy

```php
$success = $db->deleteRetentionPolicy('old_policy');
```

### Listing Retention Policies

```php
$policies = $db->getRetentionPolicies();
```

## Continuous Queries (InfluxDB 1.x) / Tasks (InfluxDB 2.x)

### Creating a Continuous Query/Task

```php
// For InfluxDB 1.x
$query = 'SELECT mean("value") AS "mean_value" INTO "hourly_cpu_usage" FROM "cpu_usage" GROUP BY time(1h), "host"';
$success = $db->createContinuousQuery('hourly_avg', $query, '1h');

// For InfluxDB 2.x, this creates a task
```

### Deleting a Continuous Query/Task

```php
$success = $db->deleteContinuousQuery('old_query');
```

### Listing Continuous Queries/Tasks

```php
$queries = $db->getContinuousQueries();
```

## Error Handling

The InfluxDB driver can throw the following exceptions:

- `ConnectionException`: When there is an error connecting to the InfluxDB server
- `ConfigurationException`: When there is an error in the configuration
- `QueryException`: When there is an error executing a query
- `WriteException`: When there is an error writing data
- `DatabaseException`: When there is an error performing database management operations

```php
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;
use TimeSeriesPhp\Exceptions\DatabaseException;
use TimeSeriesPhp\Exceptions\TSDBException;

try {
    $db = DriverManager::create('influxdb', $config);
    $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
    $db->write($dataPoint);
    $result = $db->query($query);
} catch (ConnectionException $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
} catch (QueryException $e) {
    echo "Query error: " . $e->getMessage() . "\n";
} catch (WriteException $e) {
    echo "Write error: " . $e->getMessage() . "\n";
} catch (DatabaseException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (TSDBException $e) {
    echo "Other error: " . $e->getMessage() . "\n";
}
```

## Best Practices

### Tag vs. Field Selection

In InfluxDB, tags are indexed and fields are not. Use tags for data that you will frequently filter by, and fields for the actual measurements.

```php
// Good: Host and region as tags, value as a field
$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1', 'region' => 'us-west']
);

// Bad: Host and region as fields
$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5, 'host' => 'server1', 'region' => 'us-west'],
    []
);
```

### Batch Writing

For better performance, use batch operations when writing multiple data points.

```php
$dataPoints = [];
for ($i = 0; $i < 1000; $i++) {
    $dataPoints[] = new DataPoint(
        'cpu_usage',
        ['value' => rand(0, 100)],
        ['host' => "server{$i % 10}"]
    );
}

// Write all data points in a single operation
$db->writeBatch($dataPoints);
```

### Query Optimization

- Use time ranges to limit the amount of data scanned
- Use tags for filtering
- Group by time to reduce the amount of data returned
- Use aggregations to pre-process data on the server side

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime()) // Only last hour
      ->groupByTime('5m')                                 // Group by 5-minute intervals
      ->avg('value', 'avg_value');                        // Calculate average
```

### Connection Management

Always close the connection when you're done.

```php
$db = DriverManager::create('influxdb', $config);
try {
    // Use the database
    $db->write($dataPoint);
    $result = $db->query($query);
} finally {
    // Close the connection
    $db->close();
}
```
