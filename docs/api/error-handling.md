# Error Handling in TimeSeriesPhp

This document provides detailed information about error handling in TimeSeriesPhp.

## Introduction

TimeSeriesPhp uses exceptions for error handling, providing a comprehensive exception hierarchy that allows you to catch and handle specific types of errors. All exceptions in TimeSeriesPhp extend the base `TSDBException` class, which itself extends PHP's built-in `Exception` class.

## Exception Hierarchy

The TimeSeriesPhp exception hierarchy is designed to allow you to catch specific types of exceptions or to catch all TimeSeriesPhp exceptions with a single catch block.

```
Exception (PHP built-in)
└── TSDBException
    ├── ConnectionException
    ├── ConfigurationException
    ├── DriverNotFoundException
    ├── QueryException
    ├── WriteException
    ├── DatabaseException
    ├── UnsupportedOperationException
    └── ValidationException
```

### Base Exception

```php
TimeSeriesPhp\Exceptions\TSDBException
```

The base exception class for all TimeSeriesPhp exceptions. All other exceptions in the library extend this class.

### Connection Exceptions

```php
TimeSeriesPhp\Exceptions\ConnectionException
```

Thrown when there is an error connecting to the database, such as network issues, authentication failures, or server unavailability.

### Configuration Exceptions

```php
TimeSeriesPhp\Exceptions\ConfigurationException
```

Thrown when there is an error in the configuration, such as missing required options or invalid option values.

### Driver Not Found Exceptions

```php
TimeSeriesPhp\Exceptions\DriverNotFoundException
```

Thrown when attempting to use a driver that is not registered with the factory.

### Query Exceptions

```php
TimeSeriesPhp\Exceptions\QueryException
```

Thrown when there is an error executing a query, such as syntax errors, invalid field names, or unsupported query features.

### Write Exceptions

```php
TimeSeriesPhp\Exceptions\WriteException
```

Thrown when there is an error writing data to the database, such as invalid data points, write permission issues, or server errors.

### Database Exceptions

```php
TimeSeriesPhp\Exceptions\DatabaseException
```

Thrown when there is an error performing database management operations, such as creating or deleting databases, measurements, or retention policies.

### Unsupported Operation Exceptions

```php
TimeSeriesPhp\Exceptions\UnsupportedOperationException
```

Thrown when attempting to use a feature that is not supported by the current database driver.

### Validation Exceptions

```php
TimeSeriesPhp\Exceptions\ValidationException
```

Thrown when there is an error validating input data, such as invalid data types, formats, or constraints.

## Handling Exceptions

### Basic Exception Handling

The most basic way to handle exceptions is to catch the base `TSDBException` class, which will catch all TimeSeriesPhp exceptions:

```php
use TimeSeriesPhp\Core\DriverManager;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Exceptions\TSDBException;

try {
    $db = DriverManager::create('influxdb', $config);
    $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
    $db->write($dataPoint);
} catch (TSDBException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Handling Specific Exceptions

For more granular error handling, you can catch specific exception types:

```php
use TimeSeriesPhp\Core\DriverManager;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\ConfigurationException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;
use TimeSeriesPhp\Exceptions\TSDBException;

try {
    // Create a database instance
    $db = DriverManager::create('influxdb', $config);
    
    // Write a data point
    $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
    $db->write($dataPoint);
    
    // Execute a query
    $query = new Query('cpu_usage');
    $query->select(['value'])
          ->where('host', '=', 'server1')
          ->timeRange(new DateTime('-1 hour'), new DateTime());
    $result = $db->query($query);
    
} catch (ConnectionException $e) {
    // Handle connection errors
    echo "Connection error: " . $e->getMessage() . "\n";
    // Log the error, notify administrators, etc.
    
} catch (ConfigurationException $e) {
    // Handle configuration errors
    echo "Configuration error: " . $e->getMessage() . "\n";
    // Log the error, check configuration, etc.
    
} catch (QueryException $e) {
    // Handle query errors
    echo "Query error: " . $e->getMessage() . "\n";
    // Log the error, modify the query, etc.
    
} catch (WriteException $e) {
    // Handle write errors
    echo "Write error: " . $e->getMessage() . "\n";
    // Log the error, retry the write, etc.
    
} catch (TSDBException $e) {
    // Handle other TimeSeriesPhp errors
    echo "Error: " . $e->getMessage() . "\n";
    // Log the error, etc.
}
```

### Handling Driver-Specific Exceptions

Some database drivers may throw additional driver-specific exceptions. These exceptions will still extend the base `TSDBException` class, but may provide additional information specific to the driver.

```php
use TimeSeriesPhp\Drivers\InfluxDB\Exceptions\InfluxDBAuthException;
use TimeSeriesPhp\Exceptions\TSDBException;

try {
    $db = DriverManager::create('influxdb', $config);
    // ...
} catch (InfluxDBAuthException $e) {
    // Handle InfluxDB authentication errors
    echo "InfluxDB authentication error: " . $e->getMessage() . "\n";
    // Log the error, check credentials, etc.
} catch (TSDBException $e) {
    // Handle other TimeSeriesPhp errors
    echo "Error: " . $e->getMessage() . "\n";
}
```

## Best Practices

### Use Specific Exception Types

When catching exceptions, use the most specific exception type that makes sense for your use case. This allows you to handle different types of errors differently.

```php
try {
    // ...
} catch (ConnectionException $e) {
    // Handle connection errors specifically
} catch (TSDBException $e) {
    // Handle other TimeSeriesPhp errors
}
```

### Always Catch the Base Exception

Always include a catch block for the base `TSDBException` class to ensure that all TimeSeriesPhp exceptions are caught, even if you're not specifically handling them.

```php
try {
    // ...
} catch (ConnectionException $e) {
    // Handle connection errors specifically
} catch (QueryException $e) {
    // Handle query errors specifically
} catch (TSDBException $e) {
    // Handle other TimeSeriesPhp errors
}
```

### Log Exceptions

Always log exceptions, especially in production environments. This helps with debugging and monitoring.

```php
try {
    // ...
} catch (TSDBException $e) {
    // Log the exception
    error_log("TimeSeriesPhp error: " . $e->getMessage());
    
    // Optionally re-throw the exception or handle it in some other way
    throw $e;
}
```

### Clean Up Resources

Make sure to clean up resources, such as database connections, even when exceptions occur. Use `finally` blocks for this purpose.

```php
$db = DriverManager::create('influxdb', $config);
try {
    // Use the database
    $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
    $db->write($dataPoint);
} catch (TSDBException $e) {
    // Handle the exception
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // Close the connection, regardless of whether an exception occurred
    $db->close();
}
```

### Retry Strategies

For transient errors, such as network issues, consider implementing a retry strategy.

```php
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\TSDBException;

$maxRetries = 3;
$retryDelay = 1; // seconds

for ($retry = 0; $retry <= $maxRetries; $retry++) {
    try {
        $db = DriverManager::create('influxdb', $config);
        $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
        $db->write($dataPoint);
        
        // If we get here, the operation succeeded
        break;
        
    } catch (ConnectionException $e) {
        // Connection error, retry if we haven't exceeded the maximum retries
        if ($retry < $maxRetries) {
            echo "Connection error, retrying in {$retryDelay} seconds...\n";
            sleep($retryDelay);
            $retryDelay *= 2; // Exponential backoff
        } else {
            // Maximum retries exceeded, re-throw the exception
            throw $e;
        }
    } catch (TSDBException $e) {
        // Other errors, don't retry
        throw $e;
    }
}
```

## Creating Custom Exceptions

If you're extending TimeSeriesPhp with custom functionality, you may want to create your own exception classes. Always extend the base `TSDBException` class or one of its subclasses to ensure that your exceptions can be caught with the same catch blocks.

```php
<?php

namespace MyApp\TimeSeriesPhp\Exceptions;

use TimeSeriesPhp\Exceptions\TSDBException;

class MyCustomException extends TSDBException
{
    // Add custom properties or methods as needed
}
```

Then, you can throw your custom exception in your code:

```php
use MyApp\TimeSeriesPhp\Exceptions\MyCustomException;

function myFunction()
{
    if (/* some condition */) {
        throw new MyCustomException("Something went wrong");
    }
}
```

And catch it along with other TimeSeriesPhp exceptions:

```php
use TimeSeriesPhp\Exceptions\TSDBException;
use MyApp\TimeSeriesPhp\Exceptions\MyCustomException;

try {
    myFunction();
} catch (MyCustomException $e) {
    // Handle your custom exception specifically
} catch (TSDBException $e) {
    // Handle other TimeSeriesPhp exceptions
}
```
