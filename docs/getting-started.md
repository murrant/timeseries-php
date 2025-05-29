# Getting Started with TimeSeriesPhp

This guide provides a quick introduction to the TimeSeriesPhp library, helping you get up and running quickly.

## Introduction

TimeSeriesPhp is a PHP library that provides a unified interface for interacting with various time series databases. It supports multiple database backends including InfluxDB, Prometheus, Graphite, and RRDtool, allowing you to write code that can work with any of these systems without major changes.

## Installation

You can install TimeSeriesPhp via Composer:

```bash
composer require librenms/timeseries-php
```

## Basic Usage

### Creating a Database Connection

The main entry point for TimeSeriesPhp is the `TSDBFactory` class, which creates database driver instances:

```php
<?php

use TimeSeriesPhp\Core\TSDBFactory;

// Create a database instance with default configuration
$db = TSDBFactory::create('influxdb');

// Or with explicit configuration
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;

$config = new InfluxDBConfig([
    'url' => 'http://localhost:8086',
    'token' => 'your-token',
    'org' => 'your-org',
    'bucket' => 'your-bucket',
]);

$db = TSDBFactory::create('influxdb', $config);
```

### Writing Data

To write data to a time series database, create a `DataPoint` object and use the `write()` method:

```php
<?php

use TimeSeriesPhp\Core\DataPoint;

// Create a data point
$dataPoint = new DataPoint(
    'cpu_usage',           // Measurement name
    ['value' => 85.5],     // Fields (metrics)
    ['host' => 'server1'], // Tags (dimensions)
    new DateTime()         // Timestamp (optional, defaults to now)
);

// Write the data point
$db->write($dataPoint);

// You can also write multiple data points at once
$dataPoints = [
    new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']),
    new DataPoint('memory_usage', ['value' => 75.2], ['host' => 'server1']),
];
$db->writeBatch($dataPoints);
```

### Querying Data

To query data, use the `Query` class to build a query and the `query()` method to execute it:

```php
<?php

use TimeSeriesPhp\Core\Query;

// Create a query
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime())
      ->limit(100);

// Execute the query
$result = $db->query($query);

// Process the results
foreach ($result as $row) {
    echo "Time: {$row['time']}, Value: {$row['value']}\n";
}
```

## Examples for All Supported Drivers

### InfluxDB

```php
<?php

use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;

// Create configuration
$config = new InfluxDBConfig([
    'url' => 'http://localhost:8086',
    'token' => 'your-token',
    'org' => 'your-org',
    'bucket' => 'your-bucket',
]);

// Create database instance
$db = TSDBFactory::create('influxdb', $config);

// Write data
$dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
$db->write($dataPoint);

// Query data
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime());
$result = $db->query($query);
```

### Prometheus

```php
<?php

use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig;

// Create configuration
$config = new PrometheusConfig([
    'url' => 'http://localhost:9090',
    'username' => 'your-username', // Optional
    'password' => 'your-password', // Optional
]);

// Create database instance
$db = TSDBFactory::create('prometheus', $config);

// Write data (Note: Prometheus is primarily a pull-based system)
$dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
$db->write($dataPoint);

// Query data
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime());
$result = $db->query($query);
```

### Graphite

```php
<?php

use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\Graphite\GraphiteConfig;

// Create configuration
$config = new GraphiteConfig([
    'host' => 'localhost',
    'port' => 2003,
    'protocol' => 'tcp',
]);

// Create database instance
$db = TSDBFactory::create('graphite', $config);

// Write data
$dataPoint = new DataPoint('servers.server1.cpu_usage', ['value' => 85.5]);
$db->write($dataPoint);

// Query data
$query = new Query('servers.server1.cpu_usage');
$query->select(['value'])
      ->timeRange(new DateTime('-1 hour'), new DateTime());
$result = $db->query($query);
```

### RRDtool

```php
<?php

use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;

// Create configuration
$config = new RRDtoolConfig([
    'path' => '/path/to/rrd/files',
    'rrdtool_bin' => '/usr/bin/rrdtool',
]);

// Create database instance
$db = TSDBFactory::create('rrdtool', $config);

// Write data
$dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
$db->write($dataPoint);

// Query data
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime());
$result = $db->query($query);
```

## Common Patterns and Best Practices

### Error Handling

Always wrap database operations in try-catch blocks to handle exceptions:

```php
<?php

use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;

try {
    $db = TSDBFactory::create('influxdb', $config);
    $db->write($dataPoint);
    $result = $db->query($query);
} catch (ConnectionException $e) {
    // Handle connection errors
    echo "Connection error: " . $e->getMessage();
} catch (WriteException $e) {
    // Handle write errors
    echo "Write error: " . $e->getMessage();
} catch (QueryException $e) {
    // Handle query errors
    echo "Query error: " . $e->getMessage();
} catch (TSDBException $e) {
    // Handle other errors
    echo "Error: " . $e->getMessage();
}
```

### Batch Operations

For better performance, use batch operations when writing multiple data points:

```php
<?php

$dataPoints = [];
for ($i = 0; $i < 100; $i++) {
    $dataPoints[] = new DataPoint(
        'cpu_usage',
        ['value' => rand(0, 100)],
        ['host' => "server{$i}"]
    );
}

// Write all data points in a single operation
$db->writeBatch($dataPoints);
```

### Connection Management

Always close the database connection when you're done:

```php
<?php

$db = TSDBFactory::create('influxdb', $config);
try {
    // Use the database
    $db->write($dataPoint);
    $result = $db->query($query);
} finally {
    // Close the connection
    $db->close();
}
```

### Query Optimization

When querying large datasets, use time ranges, limits, and aggregations to reduce the amount of data returned:

```php
<?php

$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime()) // Only last hour
      ->groupByTime('5m')                                 // Group by 5-minute intervals
      ->avg('value', 'avg_value')                         // Calculate average
      ->limit(100);                                       // Limit to 100 results
```

## Next Steps

For more detailed information, check out the following resources:

- [API Documentation](api/overview.md): Comprehensive API documentation
- [Cookbook](cookbook.md): Recipes for common tasks
- [Examples](../examples/): Example code for various use cases
