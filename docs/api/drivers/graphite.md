# Graphite Driver for TimeSeriesPhp

This document provides detailed information about the Graphite driver in TimeSeriesPhp.

## Introduction

The Graphite driver allows TimeSeriesPhp to connect to and interact with Graphite time series databases. Graphite is a highly scalable real-time graphing system that stores numeric time-series data and renders graphs of this data on demand.

## Configuration

The Graphite driver is configured using the `GraphiteConfig` class.

```php
use TimeSeriesPhp\Drivers\Graphite\GraphiteConfig;

$config = new GraphiteConfig([
    'host' => 'localhost',
    'port' => 2003,
    'protocol' => 'tcp',       // 'tcp' or 'udp'
    'timeout' => 10,           // Optional, in seconds
    'prefix' => 'myapp.',      // Optional, prefix for all metrics
]);
```

### Configuration Options

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `host` | string | Yes | - | The hostname or IP address of the Graphite server |
| `port` | int | Yes | - | The port number of the Graphite server (typically 2003 for the Carbon daemon) |
| `protocol` | string | No | 'tcp' | The protocol to use ('tcp' or 'udp') |
| `timeout` | int | No | 10 | The connection timeout in seconds |
| `prefix` | string | No | '' | A prefix to add to all metric names |
| `debug` | bool | No | false | Whether to enable debug mode |

## Creating a Driver Instance

```php
use TimeSeriesPhp\Core\DriverManager;
use TimeSeriesPhp\Drivers\Graphite\GraphiteConfig;

// Create configuration
$config = new GraphiteConfig([
    'host' => 'localhost',
    'port' => 2003,
    'protocol' => 'tcp',
]);

// Create driver instance
$db = DriverManager::create('graphite', $config);
```

## Writing Data

### Writing a Single Data Point

```php
use TimeSeriesPhp\Core\DataPoint;

// In Graphite, the measurement name often uses dot notation
$dataPoint = new DataPoint(
    'servers.server1.cpu_usage',
    ['value' => 85.5]
);

$success = $db->write($dataPoint);
```

### Writing Multiple Data Points

```php
use TimeSeriesPhp\Core\DataPoint;

$dataPoints = [
    new DataPoint('servers.server1.cpu_usage', ['value' => 85.5]),
    new DataPoint('servers.server1.memory_usage', ['value' => 75.2]),
    new DataPoint('servers.server1.disk_usage', ['value' => 60.8]),
];

$success = $db->writeBatch($dataPoints);
```

### Using Tags with Graphite

Newer versions of Graphite support tags. In TimeSeriesPhp, you can use tags with Graphite as follows:

```php
use TimeSeriesPhp\Core\DataPoint;

$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1', 'region' => 'us-west']
);

$success = $db->write($dataPoint);
```

This will be converted to a Graphite metric with tags, such as:
```
cpu_usage;host=server1;region=us-west
```

## Querying Data

### Using the Query Builder

```php
use TimeSeriesPhp\Core\Query;

$query = new Query('servers.server1.cpu_usage');
$query->select(['value'])
      ->timeRange(new DateTime('-1 hour'), new DateTime())
      ->groupByTime('5m')
      ->avg('value', 'avg_value');

$result = $db->query($query);

foreach ($result as $row) {
    echo "Time: {$row['time']}, Average: {$row['avg_value']}\n";
}
```

### Using Raw Queries

Graphite has its own query language based on functions. You can use raw Graphite queries as follows:

```php
$result = $db->rawQuery('averageSeries(servers.server1.cpu_usage)');

// More complex query
$result = $db->rawQuery('movingAverage(summarize(servers.server1.cpu_usage, "5min", "avg"), 10)');
```

## Graphite Query Language

Graphite's query language is function-based. While the TimeSeriesPhp query builder abstracts many common query patterns, you may need to use raw Graphite queries for more advanced use cases.

### Basic Graphite Functions

- `alias(seriesName, newName)`: Changes the name of a series
- `averageSeries(seriesName)`: Calculates the average of multiple series
- `summarize(seriesName, interval, func)`: Summarizes data points into intervals
- `movingAverage(seriesName, windowSize)`: Calculates the moving average
- `derivative(seriesName)`: Calculates the derivative of a series
- `scale(seriesName, factor)`: Multiplies each data point by a factor
- `timeShift(seriesName, timeShift)`: Shifts a series in time

### Examples

```
# Get the average of cpu_usage over 5-minute intervals
summarize(servers.server1.cpu_usage, "5min", "avg")

# Calculate the moving average with a window of 10 data points
movingAverage(servers.server1.cpu_usage, 10)

# Combine multiple functions
alias(movingAverage(summarize(servers.server1.cpu_usage, "5min", "avg"), 10), "CPU Usage MA")
```

## Error Handling

The Graphite driver can throw the following exceptions:

- `ConnectionException`: When there is an error connecting to the Graphite server
- `ConfigurationException`: When there is an error in the configuration
- `QueryException`: When there is an error executing a query
- `WriteException`: When there is an error writing data
- `UnsupportedOperationException`: When attempting to use a feature that is not supported by Graphite

```php
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;
use TimeSeriesPhp\Exceptions\UnsupportedOperationException;
use TimeSeriesPhp\Exceptions\TSDBException;

try {
    $db = DriverManager::create('graphite', $config);
    $dataPoint = new DataPoint('servers.server1.cpu_usage', ['value' => 85.5]);
    $db->write($dataPoint);
    $result = $db->query($query);
} catch (ConnectionException $e) {
    echo "Connection error: " . $e->getMessage() . "\n";
} catch (QueryException $e) {
    echo "Query error: " . $e->getMessage() . "\n";
} catch (WriteException $e) {
    echo "Write error: " . $e->getMessage() . "\n";
} catch (UnsupportedOperationException $e) {
    echo "Unsupported operation: " . $e->getMessage() . "\n";
} catch (TSDBException $e) {
    echo "Other error: " . $e->getMessage() . "\n";
}
```

## Limitations

Graphite has some limitations compared to other time series databases:

1. **Database Management**: Graphite has limited database management capabilities through its API. Many operations must be performed at the server level.

2. **Retention Policies**: Retention is configured at the server level in the storage-schemas.conf file and cannot be managed through the API.

3. **Continuous Queries**: Graphite does not support continuous queries in the same way as other time series databases.

4. **Tags**: Older versions of Graphite do not support tags. In these versions, you need to encode dimensions in the metric name using dot notation.

## Best Practices

### Metric Naming

In Graphite, the metric name is important for organization and querying. Use a consistent naming scheme:

```php
// Good: Hierarchical naming scheme
$dataPoint = new DataPoint(
    'servers.server1.cpu.usage_user',
    ['value' => 85.5]
);

// Bad: Inconsistent naming
$dataPoint = new DataPoint(
    'server1_cpu_user',
    ['value' => 85.5]
);
```

### Using Prefixes

Use the `prefix` configuration option to add a common prefix to all metrics:

```php
$config = new GraphiteConfig([
    'host' => 'localhost',
    'port' => 2003,
    'protocol' => 'tcp',
    'prefix' => 'myapp.',
]);

// This will be sent as 'myapp.servers.server1.cpu_usage'
$dataPoint = new DataPoint(
    'servers.server1.cpu_usage',
    ['value' => 85.5]
);
```

### Batch Writing

For better performance, use batch operations when writing multiple data points:

```php
$dataPoints = [];
for ($i = 0; $i < 1000; $i++) {
    $dataPoints[] = new DataPoint(
        "servers.server{$i}.cpu_usage",
        ['value' => rand(0, 100)]
    );
}

// Write all data points in a single operation
$db->writeBatch($dataPoints);
```

### Query Optimization

- Use time ranges to limit the amount of data scanned
- Use aggregation functions to reduce the amount of data returned
- Be mindful of the number of metrics being queried, as Graphite can be slow with large numbers of metrics

```php
$query = new Query('servers.server1.cpu_usage');
$query->select(['value'])
      ->timeRange(new DateTime('-1 hour'), new DateTime()) // Only last hour
      ->groupByTime('5m')                                 // Group by 5-minute intervals
      ->avg('value', 'avg_value');                        // Calculate average
```

### Connection Management

Always close the connection when you're done:

```php
$db = DriverManager::create('graphite', $config);
try {
    // Use the database
    $db->write($dataPoint);
    $result = $db->query($query);
} finally {
    // Close the connection
    $db->close();
}
```

### Protocol Selection

Choose the appropriate protocol based on your needs:

- **TCP**: Reliable delivery, but slower and can block if the server is slow
- **UDP**: Faster and non-blocking, but can lose data if the network is congested

```php
// For reliability
$config = new GraphiteConfig([
    'host' => 'localhost',
    'port' => 2003,
    'protocol' => 'tcp',
]);

// For performance
$config = new GraphiteConfig([
    'host' => 'localhost',
    'port' => 2003,
    'protocol' => 'udp',
]);
```
