# RRDtool Driver for TimeSeriesPhp

This document provides detailed information about the RRDtool driver in TimeSeriesPhp.

## Introduction

The RRDtool driver allows TimeSeriesPhp to connect to and interact with RRD (Round Robin Database) files. RRDtool is a high-performance data logging and graphing system for time series data, designed to store and display data like network bandwidth, temperatures, CPU load, etc.

## Configuration

The RRDtool driver is configured using the `RRDtoolConfig` class.

```php
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;

$config = new RRDtoolConfig([
    'path' => '/path/to/rrd/files',
    'rrdtool_bin' => '/usr/bin/rrdtool',  // Path to the rrdtool binary
    'temp_dir' => '/tmp',                 // Optional, directory for temporary files
    'default_step' => 300,                // Optional, default step size in seconds (5 minutes)
]);
```

### Configuration Options

| Option | Type | Required | Default | Description |
|--------|------|----------|---------|-------------|
| `path` | string | Yes | - | The directory where RRD files are stored |
| `rrdtool_bin` | string | Yes | - | The path to the rrdtool binary |
| `temp_dir` | string | No | '/tmp' | The directory for temporary files |
| `default_step` | int | No | 300 | The default step size in seconds |
| `debug` | bool | No | false | Whether to enable debug mode |

## Creating a Driver Instance

```php
use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;

// Create configuration
$config = new RRDtoolConfig([
    'path' => '/path/to/rrd/files',
    'rrdtool_bin' => '/usr/bin/rrdtool',
]);

// Create driver instance
$db = TSDBFactory::create('rrdtool', $config);
```

## RRD File Structure

Before using the RRDtool driver, it's important to understand how RRD files work:

1. **Data Sources (DS)**: Define what data is stored (e.g., 'cpu_usage', 'memory_usage')
2. **Round Robin Archives (RRA)**: Define how data is stored over time (resolution and retention)
3. **Step Size**: The interval at which data is expected (e.g., every 5 minutes)

RRD files must be created with a predefined structure before data can be written to them.

## Creating RRD Files

The RRDtool driver provides methods to create RRD files:

```php
// Create an RRD file with a single data source
$success = $db->createRRDFile(
    'server1_cpu.rrd',           // File name
    300,                         // Step size (5 minutes)
    [                            // Data sources
        [
            'name' => 'usage',   // DS name
            'type' => 'GAUGE',   // DS type (GAUGE, COUNTER, DERIVE, ABSOLUTE)
            'min' => 0,          // Minimum value
            'max' => 100,        // Maximum value
            'heartbeat' => 600   // Heartbeat (2 * step)
        ]
    ],
    [                            // Round Robin Archives
        [
            'cf' => 'AVERAGE',   // Consolidation function (AVERAGE, MIN, MAX, LAST)
            'steps' => 1,        // Steps per data point
            'rows' => 2016       // Number of data points to store (1 week at 5-minute intervals)
        ],
        [
            'cf' => 'AVERAGE',
            'steps' => 12,       // 1 hour (12 * 5 minutes)
            'rows' => 1440       // 2 months of hourly data
        ],
        [
            'cf' => 'AVERAGE',
            'steps' => 288,      // 1 day (288 * 5 minutes)
            'rows' => 365        // 1 year of daily data
        ]
    ]
);
```

## Writing Data

### Writing a Single Data Point

```php
use TimeSeriesPhp\Core\DataPoint;

// The measurement name should match the RRD file name (without .rrd extension)
// The field name should match the data source name
$dataPoint = new DataPoint(
    'server1_cpu',
    ['usage' => 85.5]
);

$success = $db->write($dataPoint);
```

### Writing Multiple Data Points

```php
use TimeSeriesPhp\Core\DataPoint;

$dataPoints = [
    new DataPoint('cpu', ['usage' => 85.5], ['host' => 'server1']),
    new DataPoint('memory', ['usage' => 75.2], ['host' => 'server1']),
    new DataPoint('disk', ['usage' => 60.8], ['host' => 'server1']),
];

$success = $db->writeBatch($dataPoints);
```

### Writing Data with a Specific Timestamp

```php
use TimeSeriesPhp\Core\DataPoint;

$dataPoint = new DataPoint(
    'cpu',
    ['usage' => 85.5],
    ['host' => 'server1'],
    new DateTime('2023-01-01 12:00:00')
);

$success = $db->write($dataPoint);
```

## Querying Data

### Using the Query Builder

```php
use TimeSeriesPhp\Core\Query;

$query = new Query('cpu', );
$query->select(['usage'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 day'), new DateTime())
      ->groupByTime('1h')
      ->avg('usage', 'avg_usage');

$result = $db->query($query);

foreach ($result as $row) {
    echo "Time: {$row['time']}, Average: {$row['avg_usage']}\n";
}
```

### Using Raw Queries

RRDtool has its own command-line interface for querying data. You can use raw RRDtool commands as follows:

```php
// Fetch data from an RRD file
$result = $db->rawQuery('fetch server1_cpu.rrd AVERAGE --start -1d --end now');

// More complex query using xport
$result = $db->rawQuery('xport --start -1d --end now DEF:cpu=server1_cpu.rrd:usage:AVERAGE XPORT:cpu:"CPU Usage"');
```

## RRDtool Commands

RRDtool provides several commands for working with RRD files. While the TimeSeriesPhp driver abstracts many common operations, you may need to use raw RRDtool commands for more advanced use cases.

### Basic RRDtool Commands

- `create`: Creates a new RRD file
- `update`: Adds new data to an RRD file
- `fetch`: Retrieves data from an RRD file
- `graph`: Creates a graph from RRD data
- `xport`: Exports data from an RRD file in XML format
- `info`: Displays information about an RRD file
- `dump`: Dumps the contents of an RRD file in XML format
- `restore`: Restores an RRD file from an XML dump

### Examples

```
# Create an RRD file
rrdtool create server1_cpu.rrd --step 300 DS:usage:GAUGE:600:0:100 RRA:AVERAGE:0.5:1:2016

# Update an RRD file
rrdtool update server1_cpu.rrd N:85.5

# Fetch data from an RRD file
rrdtool fetch server1_cpu.rrd AVERAGE --start -1d --end now

# Create a graph
rrdtool graph cpu.png --start -1d --end now DEF:cpu=server1_cpu.rrd:usage:AVERAGE LINE1:cpu#FF0000:"CPU Usage"
```

## Error Handling

The RRDtool driver can throw the following exceptions:

- `ConfigurationException`: When there is an error in the configuration
- `QueryException`: When there is an error executing a query
- `WriteException`: When there is an error writing data
- `DatabaseException`: When there is an error performing database management operations
- `UnsupportedOperationException`: When attempting to use a feature that is not supported by RRDtool

```php
use TimeSeriesPhp\Exceptions\ConfigurationException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\WriteException;
use TimeSeriesPhp\Exceptions\DatabaseException;
use TimeSeriesPhp\Exceptions\UnsupportedOperationException;
use TimeSeriesPhp\Exceptions\TSDBException;

try {
    $db = TSDBFactory::create('rrdtool', $config);
    $dataPoint = new DataPoint('server1_cpu', ['usage' => 85.5]);
    $db->write($dataPoint);
    $result = $db->query($query);
} catch (ConfigurationException $e) {
    echo "Configuration error: " . $e->getMessage() . "\n";
} catch (QueryException $e) {
    echo "Query error: " . $e->getMessage() . "\n";
} catch (WriteException $e) {
    echo "Write error: " . $e->getMessage() . "\n";
} catch (DatabaseException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (UnsupportedOperationException $e) {
    echo "Unsupported operation: " . $e->getMessage() . "\n";
} catch (TSDBException $e) {
    echo "Other error: " . $e->getMessage() . "\n";
}
```

## Limitations

RRDtool has some limitations compared to other time series databases:

1. **Pre-defined Structure**: RRD files must be created with a predefined structure before data can be written to them.

2. **Fixed Resolution**: Data is stored at fixed resolutions defined by the RRA steps.

3. **No Tags/Labels**: RRDtool does not support tags or labels for filtering data.

4. **Limited Query Capabilities**: RRDtool has limited query capabilities compared to other time series databases.

5. **File-based Storage**: RRDtool uses a file-based storage model, which can be less scalable than other time series databases.

## Best Practices

### RRD File Design

Carefully design your RRD files to balance storage requirements and query performance:

- Choose appropriate step sizes based on your data collection frequency
- Define RRAs with appropriate consolidation functions and retention periods
- Use consistent naming conventions for RRD files and data sources

```php
// Example of a well-designed RRD file
$success = $db->createRRDFile(
    'server1_cpu.rrd',
    300,                         // 5-minute step
    [
        ['name' => 'usage', 'type' => 'GAUGE', 'min' => 0, 'max' => 100, 'heartbeat' => 600]
    ],
    [
        // High-resolution data for recent queries
        ['cf' => 'AVERAGE', 'steps' => 1, 'rows' => 2016],    // 1 week at 5-minute intervals
        
        // Medium-resolution data for intermediate-term queries
        ['cf' => 'AVERAGE', 'steps' => 12, 'rows' => 1440],   // 2 months of hourly data
        
        // Low-resolution data for long-term queries
        ['cf' => 'AVERAGE', 'steps' => 288, 'rows' => 365],   // 1 year of daily data
        
        // Min/Max values for high-resolution data
        ['cf' => 'MIN', 'steps' => 1, 'rows' => 2016],        // 1 week of minimum values
        ['cf' => 'MAX', 'steps' => 1, 'rows' => 2016]         // 1 week of maximum values
    ]
);
```

### Data Source Types

Choose the appropriate data source type based on your data:

- **GAUGE**: For values that can increase or decrease (e.g., temperature, CPU usage)
- **COUNTER**: For continuously increasing values (e.g., network traffic, disk I/O)
- **DERIVE**: Similar to COUNTER, but can handle decreases (e.g., error counts)
- **ABSOLUTE**: For counters that reset on each reading (e.g., interrupt counts)

```php
// Example of different data source types
$success = $db->createRRDFile(
    'server1_metrics.rrd',
    300,
    [
        ['name' => 'cpu', 'type' => 'GAUGE', 'min' => 0, 'max' => 100, 'heartbeat' => 600],
        ['name' => 'network_in', 'type' => 'COUNTER', 'min' => 0, 'max' => 'U', 'heartbeat' => 600],
        ['name' => 'errors', 'type' => 'DERIVE', 'min' => 0, 'max' => 'U', 'heartbeat' => 600],
        ['name' => 'interrupts', 'type' => 'ABSOLUTE', 'min' => 0, 'max' => 'U', 'heartbeat' => 600]
    ],
    [
        ['cf' => 'AVERAGE', 'steps' => 1, 'rows' => 2016]
    ]
);
```

### Query Optimization

- Match your query time ranges to the available RRA resolutions
- Use the appropriate consolidation function for your use case
- Be mindful of the step size when querying data

```php
$query = new Query('server1_cpu');
$query->select(['usage'])
      ->timeRange(new DateTime('-1 day'), new DateTime()) // Recent data, use high-resolution RRA
      ->groupByTime('1h')                                // Group by 1 hour
      ->avg('usage', 'avg_usage');                       // Calculate average

$query = new Query('server1_cpu');
$query->select(['usage'])
      ->timeRange(new DateTime('-1 month'), new DateTime()) // Older data, use medium-resolution RRA
      ->groupByTime('1d')                                  // Group by 1 day
      ->avg('usage', 'avg_usage');                         // Calculate average
```

### File Management

Regularly check and maintain your RRD files:

- Monitor disk space usage
- Back up RRD files regularly
- Use the `info` command to check the structure of RRD files
- Use the `dump` and `restore` commands for backup and migration

```php
// Check the structure of an RRD file
$info = $db->rawQuery('info server1_cpu.rrd');

// Dump an RRD file to XML
$dump = $db->rawQuery('dump server1_cpu.rrd > server1_cpu.xml');

// Restore an RRD file from XML
$restore = $db->rawQuery('restore server1_cpu.xml server1_cpu.rrd');
```
