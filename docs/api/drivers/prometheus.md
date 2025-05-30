# Prometheus Driver for TimeSeriesPhp

This document provides detailed information about the Prometheus driver in TimeSeriesPhp.

## Introduction

The Prometheus driver allows TimeSeriesPhp to connect to and interact with Prometheus time series databases. Prometheus is an open-source monitoring and alerting toolkit designed for reliability and scalability.

## Configuration

The Prometheus driver is configured using the `PrometheusConfig` class.

```php
use TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig;

$config = new PrometheusConfig([
    'url' => 'http://localhost:9090',
    'username' => 'your-username', // Optional
    'password' => 'your-password', // Optional
    'timeout' => 10,               // Optional, in seconds
    'verify_ssl' => true,          // Optional
]);
```

### Configuration Options

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `url` | string | Yes | - | The URL of the Prometheus server |
| `username` | string | No | null | The username for basic authentication |
| `password` | string | No | null | The password for basic authentication |
| `timeout` | int | No | 10 | The connection timeout in seconds |
| `verify_ssl` | bool | No | true | Whether to verify SSL certificates |
| `debug` | bool | No | false | Whether to enable debug mode |

## Creating a Driver Instance

```php
use TimeSeriesPhp\Core\DriverManager;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig;

// Create configuration
$config = new PrometheusConfig([
    'url' => 'http://localhost:9090',
    'username' => 'your-username', // Optional
    'password' => 'your-password', // Optional
]);

// Create driver instance
$db = DriverManager::create('prometheus', $config);
```

## Writing Data

Prometheus is primarily a pull-based system, where metrics are scraped from instrumented applications. However, TimeSeriesPhp provides a way to push metrics to Prometheus via the Prometheus Pushgateway.

### Writing a Single Data Point

```php
use TimeSeriesPhp\Core\DataPoint;

$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1', 'job' => 'node']
);

$success = $db->write($dataPoint);
```

### Writing Multiple Data Points

```php
use TimeSeriesPhp\Core\DataPoint;

$dataPoints = [
    new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1', 'job' => 'node']),
    new DataPoint('memory_usage', ['value' => 75.2], ['host' => 'server1', 'job' => 'node']),
    new DataPoint('disk_usage', ['value' => 60.8], ['host' => 'server1', 'job' => 'node']),
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

### Using Raw Queries (PromQL)

```php
// Simple PromQL query
$result = $db->rawQuery('cpu_usage{host="server1"}[1h]');

// More complex PromQL query
$result = $db->rawQuery('avg_over_time(cpu_usage{host="server1"}[5m])[1h:5m]');
```

## Prometheus Query Language (PromQL)

Prometheus has its own query language called PromQL. While the TimeSeriesPhp query builder abstracts many common query patterns, you may need to use raw PromQL queries for more advanced use cases.

### Basic PromQL Syntax

```
<metric_name>{<label_name>=<label_value>, ...}
```

### Time Range Selection

```
<expression>[<duration>]
```

### Aggregation

```
<aggr_op>(<expression>)
```

### Examples

```
# Select the cpu_usage metric for server1
cpu_usage{host="server1"}

# Select the cpu_usage metric for server1 over the last hour
cpu_usage{host="server1"}[1h]

# Calculate the average cpu_usage for server1 over 5-minute windows
avg_over_time(cpu_usage{host="server1"}[5m])

# Calculate the average cpu_usage for all servers, grouped by host
avg by (host) (cpu_usage)
```

## Error Handling

The Prometheus driver can throw the following exceptions:

- `ConnectionException`: When there is an error connecting to the Prometheus server
- `ConfigurationException`: When there is an error in the configuration
- `QueryException`: When there is an error executing a query
- `WriteException`: When there is an error writing data
- `UnsupportedOperationException`: When attempting to use a feature that is not supported by Prometheus

```php
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;
use TimeSeriesPhp\Exceptions\UnsupportedOperationException;
use TimeSeriesPhp\Exceptions\TSDBException;

try {
    $db = DriverManager::create('prometheus', $config);
    $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1', 'job' => 'node']);
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

Prometheus has some limitations compared to other time series databases:

1. **Database Management**: Prometheus does not support creating or deleting databases through its API. These operations must be performed at the server level.

2. **Write Model**: Prometheus is primarily a pull-based system. The TimeSeriesPhp driver uses the Prometheus Pushgateway for writing data, which is intended for service-level batch jobs rather than high-volume metrics collection.

3. **Retention Policies**: Retention is configured at the server level and cannot be managed through the API.

4. **Continuous Queries**: Prometheus does not support continuous queries in the same way as other time series databases. Similar functionality can be achieved using recording rules, but these must be configured at the server level.

## Best Practices

### Label vs. Value Selection

In Prometheus, labels (tags in TimeSeriesPhp) are used for filtering and grouping, while values are used for the actual measurements.

```php
// Good: Host and job as labels, value as a field
$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1', 'job' => 'node']
);

// Bad: Host and job as fields
$dataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5, 'host' => 'server1', 'job' => 'node'],
    []
);
```

### Label Cardinality

Be careful with high-cardinality labels (labels with many possible values), as they can impact Prometheus performance.

```php
// Good: Low-cardinality labels
$dataPoint = new DataPoint(
    'http_requests_total',
    ['value' => 1],
    ['method' => 'GET', 'endpoint' => '/api/users', 'status' => '200']
);

// Bad: High-cardinality labels
$dataPoint = new DataPoint(
    'http_requests_total',
    ['value' => 1],
    ['user_id' => '12345', 'request_id' => 'abcdef123456', 'timestamp' => '1620000000']
);
```

### Query Optimization

- Use time ranges to limit the amount of data scanned
- Use labels for filtering
- Use aggregation to reduce the amount of data returned

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
$db = DriverManager::create('prometheus', $config);
try {
    // Use the database
    $result = $db->query($query);
} finally {
    // Close the connection
    $db->close();
}
```
