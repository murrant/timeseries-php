# Database Management in TimeSeriesPhp

This document provides detailed information about database management operations in TimeSeriesPhp.

## Introduction

TimeSeriesPhp provides a set of methods for managing time series databases, including creating and deleting databases, managing measurements, and performing other administrative tasks. These operations are available through the database driver instances.

## Database Operations

### Creating a Database

```php
createDatabase(string $name): bool
```

Creates a new database.

#### Parameters

- `$name` (string): The name of the database to create

#### Returns

- `bool`: True if the database was created successfully, false otherwise

#### Exceptions

- `DatabaseException`: If there was an error creating the database

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    $success = $db->createDatabase('new_database');
    if ($success) {
        echo "Database created successfully.\n";
    } else {
        echo "Failed to create database.\n";
    }
} catch (DatabaseException $e) {
    echo "Error creating database: " . $e->getMessage() . "\n";
}
```

### Deleting a Database

```php
deleteDatabase(string $name): bool
```

Deletes a database.

#### Parameters

- `$name` (string): The name of the database to delete

#### Returns

- `bool`: True if the database was deleted successfully, false otherwise

#### Exceptions

- `DatabaseException`: If there was an error deleting the database

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    $success = $db->deleteDatabase('old_database');
    if ($success) {
        echo "Database deleted successfully.\n";
    } else {
        echo "Failed to delete database.\n";
    }
} catch (DatabaseException $e) {
    echo "Error deleting database: " . $e->getMessage() . "\n";
}
```

### Listing Databases

```php
getDatabases(): array
```

Gets a list of all databases.

#### Returns

- `array`: An array of database names

#### Exceptions

- `DatabaseException`: If there was an error retrieving the database list

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    $databases = $db->getDatabases();
    echo "Available databases:\n";
    foreach ($databases as $database) {
        echo "- $database\n";
    }
} catch (DatabaseException $e) {
    echo "Error retrieving databases: " . $e->getMessage() . "\n";
}
```

## Measurement Operations

### Deleting a Measurement

```php
deleteMeasurement(string $measurement, ?DateTime $start = null, ?DateTime $end = null): bool
```

Deletes a measurement or data within a specific time range.

#### Parameters

- `$measurement` (string): The name of the measurement to delete
- `$start` (DateTime, optional): The start time of the range to delete
- `$end` (DateTime, optional): The end time of the range to delete

#### Returns

- `bool`: True if the measurement was deleted successfully, false otherwise

#### Exceptions

- `DatabaseException`: If there was an error deleting the measurement

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);

// Delete an entire measurement
try {
    $success = $db->deleteMeasurement('cpu_usage');
    if ($success) {
        echo "Measurement deleted successfully.\n";
    } else {
        echo "Failed to delete measurement.\n";
    }
} catch (DatabaseException $e) {
    echo "Error deleting measurement: " . $e->getMessage() . "\n";
}

// Delete data within a specific time range
try {
    $start = new DateTime('-1 day');
    $end = new DateTime();
    $success = $db->deleteMeasurement('cpu_usage', $start, $end);
    if ($success) {
        echo "Data deleted successfully.\n";
    } else {
        echo "Failed to delete data.\n";
    }
} catch (DatabaseException $e) {
    echo "Error deleting data: " . $e->getMessage() . "\n";
}
```

### Listing Measurements

```php
getMeasurements(): array
```

Gets a list of all measurements in the database.

#### Returns

- `array`: An array of measurement names

#### Exceptions

- `DatabaseException`: If there was an error retrieving the measurement list

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    $measurements = $db->getMeasurements();
    echo "Available measurements:\n";
    foreach ($measurements as $measurement) {
        echo "- $measurement\n";
    }
} catch (DatabaseException $e) {
    echo "Error retrieving measurements: " . $e->getMessage() . "\n";
}
```

## Retention Policy Management

Some time series databases support retention policies, which define how long data is kept before being automatically deleted.

### Creating a Retention Policy

```php
createRetentionPolicy(string $name, string $duration, int $replication = 1, bool $default = false): bool
```

Creates a new retention policy.

#### Parameters

- `$name` (string): The name of the retention policy
- `$duration` (string): The duration to keep data (e.g., '7d', '4w', '1y')
- `$replication` (int, default: 1): The replication factor
- `$default` (bool, default: false): Whether this should be the default retention policy

#### Returns

- `bool`: True if the retention policy was created successfully, false otherwise

#### Exceptions

- `DatabaseException`: If there was an error creating the retention policy
- `UnsupportedOperationException`: If the database driver does not support retention policies

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    // Create a retention policy that keeps data for 30 days
    $success = $db->createRetentionPolicy('month', '30d', 1, true);
    if ($success) {
        echo "Retention policy created successfully.\n";
    } else {
        echo "Failed to create retention policy.\n";
    }
} catch (DatabaseException $e) {
    echo "Error creating retention policy: " . $e->getMessage() . "\n";
} catch (UnsupportedOperationException $e) {
    echo "This database does not support retention policies.\n";
}
```

### Deleting a Retention Policy

```php
deleteRetentionPolicy(string $name): bool
```

Deletes a retention policy.

#### Parameters

- `$name` (string): The name of the retention policy to delete

#### Returns

- `bool`: True if the retention policy was deleted successfully, false otherwise

#### Exceptions

- `DatabaseException`: If there was an error deleting the retention policy
- `UnsupportedOperationException`: If the database driver does not support retention policies

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    $success = $db->deleteRetentionPolicy('old_policy');
    if ($success) {
        echo "Retention policy deleted successfully.\n";
    } else {
        echo "Failed to delete retention policy.\n";
    }
} catch (DatabaseException $e) {
    echo "Error deleting retention policy: " . $e->getMessage() . "\n";
} catch (UnsupportedOperationException $e) {
    echo "This database does not support retention policies.\n";
}
```

### Listing Retention Policies

```php
getRetentionPolicies(): array
```

Gets a list of all retention policies in the database.

#### Returns

- `array`: An array of retention policy information

#### Exceptions

- `DatabaseException`: If there was an error retrieving the retention policy list
- `UnsupportedOperationException`: If the database driver does not support retention policies

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    $policies = $db->getRetentionPolicies();
    echo "Available retention policies:\n";
    foreach ($policies as $policy) {
        echo "- {$policy['name']}: {$policy['duration']}\n";
    }
} catch (DatabaseException $e) {
    echo "Error retrieving retention policies: " . $e->getMessage() . "\n";
} catch (UnsupportedOperationException $e) {
    echo "This database does not support retention policies.\n";
}
```

## Continuous Query Management

Some time series databases support continuous queries, which are queries that run automatically and periodically on the server.

### Creating a Continuous Query

```php
createContinuousQuery(string $name, string $query, string $interval): bool
```

Creates a new continuous query.

#### Parameters

- `$name` (string): The name of the continuous query
- `$query` (string): The query to execute
- `$interval` (string): The interval at which to execute the query (e.g., '1h', '1d')

#### Returns

- `bool`: True if the continuous query was created successfully, false otherwise

#### Exceptions

- `DatabaseException`: If there was an error creating the continuous query
- `UnsupportedOperationException`: If the database driver does not support continuous queries

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    // Create a continuous query that calculates hourly averages
    $query = 'SELECT mean("value") AS "mean_value" INTO "hourly_cpu_usage" FROM "cpu_usage" GROUP BY time(1h), "host"';
    $success = $db->createContinuousQuery('hourly_avg', $query, '1h');
    if ($success) {
        echo "Continuous query created successfully.\n";
    } else {
        echo "Failed to create continuous query.\n";
    }
} catch (DatabaseException $e) {
    echo "Error creating continuous query: " . $e->getMessage() . "\n";
} catch (UnsupportedOperationException $e) {
    echo "This database does not support continuous queries.\n";
}
```

### Deleting a Continuous Query

```php
deleteContinuousQuery(string $name): bool
```

Deletes a continuous query.

#### Parameters

- `$name` (string): The name of the continuous query to delete

#### Returns

- `bool`: True if the continuous query was deleted successfully, false otherwise

#### Exceptions

- `DatabaseException`: If there was an error deleting the continuous query
- `UnsupportedOperationException`: If the database driver does not support continuous queries

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    $success = $db->deleteContinuousQuery('old_query');
    if ($success) {
        echo "Continuous query deleted successfully.\n";
    } else {
        echo "Failed to delete continuous query.\n";
    }
} catch (DatabaseException $e) {
    echo "Error deleting continuous query: " . $e->getMessage() . "\n";
} catch (UnsupportedOperationException $e) {
    echo "This database does not support continuous queries.\n";
}
```

### Listing Continuous Queries

```php
getContinuousQueries(): array
```

Gets a list of all continuous queries in the database.

#### Returns

- `array`: An array of continuous query information

#### Exceptions

- `DatabaseException`: If there was an error retrieving the continuous query list
- `UnsupportedOperationException`: If the database driver does not support continuous queries

#### Examples

```php
$db = TSDBFactory::create('influxdb', $config);
try {
    $queries = $db->getContinuousQueries();
    echo "Available continuous queries:\n";
    foreach ($queries as $query) {
        echo "- {$query['name']}: {$query['query']}\n";
    }
} catch (DatabaseException $e) {
    echo "Error retrieving continuous queries: " . $e->getMessage() . "\n";
} catch (UnsupportedOperationException $e) {
    echo "This database does not support continuous queries.\n";
}
```

## Driver-Specific Considerations

Different time series database drivers may have different capabilities and limitations when it comes to database management:

### InfluxDB

- Supports databases (called "buckets" in InfluxDB 2.x)
- Supports retention policies
- Supports continuous queries (called "tasks" in InfluxDB 2.x)

### Prometheus

- Does not support creating or deleting databases
- Uses a file-based storage model
- Retention is configured at the server level

### Graphite

- Limited database management capabilities
- Retention is configured at the server level

### RRDtool

- Uses a file-based storage model
- Retention is defined when creating the RRD file
- Limited database management capabilities
