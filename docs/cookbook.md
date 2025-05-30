# TimeSeriesPhp Cookbook

This document provides recipes for common tasks when working with TimeSeriesPhp.

## Table of Contents

1. [Setting Up Time-Based Aggregations](#setting-up-time-based-aggregations)
2. [Implementing Caching Strategies](#implementing-caching-strategies)
3. [Handling High-Volume Writes](#handling-high-volume-writes)
4. [Optimizing Queries for Performance](#optimizing-queries-for-performance)
5. [Migrating Between Database Backends](#migrating-between-database-backends)
6. [Working with Tags and Fields](#working-with-tags-and-fields)
7. [Error Handling and Retry Strategies](#error-handling-and-retry-strategies)
8. [Using with Laravel](#using-with-laravel)

## Setting Up Time-Based Aggregations

Time-based aggregations are a common requirement when working with time series data. They allow you to reduce the granularity of your data over time, which can improve query performance and reduce storage requirements.

### Basic Time-Based Aggregation

```php
use TimeSeriesPhp\Core\Query;

// Query with time-based aggregation
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 day'), new DateTime())
      ->groupByTime('1h')           // Group by 1-hour intervals
      ->avg('value', 'avg_value')   // Calculate average
      ->min('value', 'min_value')   // Calculate minimum
      ->max('value', 'max_value');  // Calculate maximum

$result = $db->query($query);

// Process the results
foreach ($result as $row) {
    echo "Time: {$row['time']}, " .
         "Avg: {$row['avg_value']}, " .
         "Min: {$row['min_value']}, " .
         "Max: {$row['max_value']}\n";
}
```

### Downsampling with Different Time Windows

```php
// Last hour: 1-minute intervals
$hourQuery = new Query('cpu_usage');
$hourQuery->select(['value'])
          ->timeRange(new DateTime('-1 hour'), new DateTime())
          ->groupByTime('1m')
          ->avg('value', 'avg_value');

// Last day: 1-hour intervals
$dayQuery = new Query('cpu_usage');
$dayQuery->select(['value'])
         ->timeRange(new DateTime('-1 day'), new DateTime())
         ->groupByTime('1h')
         ->avg('value', 'avg_value');

// Last week: 1-day intervals
$weekQuery = new Query('cpu_usage');
$weekQuery->select(['value'])
          ->timeRange(new DateTime('-1 week'), new DateTime())
          ->groupByTime('1d')
          ->avg('value', 'avg_value');
```

### Filling Missing Values

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->timeRange(new DateTime('-1 day'), new DateTime())
      ->groupByTime('1h')
      ->avg('value', 'avg_value')
      ->fillNull();  // Fill missing values with null

// Other fill options:
// $query->fillNone();      // No filling
// $query->fillPrevious();  // Fill with previous value
// $query->fillLinear();    // Linear interpolation
// $query->fillValue(0);    // Fill with a specific value
```

### Continuous Queries (Database-Side Aggregation)

Some time series databases support continuous queries, which automatically compute aggregations in the background.

```php
// Example for InfluxDB
$db = DriverManager::create('influxdb', $config);

// Create a continuous query that computes hourly averages
$query = 'SELECT mean("value") AS "hourly_avg" INTO "hourly_cpu_usage" FROM "cpu_usage" GROUP BY time(1h), "host"';
$db->createContinuousQuery('hourly_avg', $query, '1h');

// Query the pre-computed aggregations
$query = new Query('hourly_cpu_usage');
$query->select(['hourly_avg'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 week'), new DateTime());
$result = $db->query($query);
```

## Implementing Caching Strategies

Caching can significantly improve performance when working with time series data, especially for frequently accessed queries.

### Simple Query Result Caching

```php
use Psr\SimpleCache\CacheInterface;

class CachedTimeSeriesDB
{
    private $db;
    private $cache;
    private $ttl;

    public function __construct(TimeSeriesInterface $db, CacheInterface $cache, int $ttl = 300)
    {
        $this->db = $db;
        $this->cache = $cache;
        $this->ttl = $ttl;
    }

    public function query(Query $query): QueryResult
    {
        // Generate a cache key based on the query
        $cacheKey = 'tsdb_query_' . md5(serialize($query));

        // Try to get the result from cache
        $cachedResult = $this->cache->get($cacheKey);
        if ($cachedResult !== null) {
            return $cachedResult;
        }

        // Execute the query
        $result = $this->db->query($query);

        // Cache the result
        $this->cache->set($cacheKey, $result, $this->ttl);

        return $result;
    }

    // Proxy other methods to the underlying database
    public function write(DataPoint $dataPoint): bool
    {
        return $this->db->write($dataPoint);
    }

    // ... other methods
}

// Usage
$db = DriverManager::create('influxdb', $config);
$cache = new SomeCache(); // PSR-16 compatible cache
$cachedDb = new CachedTimeSeriesDB($db, $cache, 300); // 5-minute TTL

$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime());

// This will use the cache if available
$result = $cachedDb->query($query);
```

### Time-Based Cache Invalidation

```php
class TimeAwareCachedTimeSeriesDB extends CachedTimeSeriesDB
{
    public function query(Query $query): QueryResult
    {
        // For queries with recent time ranges, bypass the cache
        $end = $query->getEndTime() ?? new DateTime();
        $now = new DateTime();
        $diff = $now->getTimestamp() - $end->getTimestamp();

        // If the query includes data from the last 5 minutes, bypass the cache
        if ($diff < 300) {
            return $this->db->query($query);
        }

        // Otherwise, use the cache
        return parent::query($query);
    }
}
```

### Caching with Different TTLs Based on Time Range

```php
class SmartCachedTimeSeriesDB extends CachedTimeSeriesDB
{
    public function query(Query $query): QueryResult
    {
        // Determine TTL based on the query's time range
        $start = $query->getStartTime();
        $end = $query->getEndTime() ?? new DateTime();
        $now = new DateTime();
        
        $ttl = $this->ttl; // Default TTL
        
        // For historical data (older than 1 day), use a longer TTL
        if ($end->getTimestamp() < $now->getTimestamp() - 86400) {
            $ttl = 3600; // 1 hour
        }
        
        // For very old data (older than 1 week), use an even longer TTL
        if ($end->getTimestamp() < $now->getTimestamp() - 604800) {
            $ttl = 86400; // 1 day
        }
        
        // Generate a cache key based on the query
        $cacheKey = 'tsdb_query_' . md5(serialize($query));
        
        // Try to get the result from cache
        $cachedResult = $this->cache->get($cacheKey);
        if ($cachedResult !== null) {
            return $cachedResult;
        }
        
        // Execute the query
        $result = $this->db->query($query);
        
        // Cache the result with the determined TTL
        $this->cache->set($cacheKey, $result, $ttl);
        
        return $result;
    }
}
```

## Handling High-Volume Writes

When dealing with high-volume writes, it's important to optimize your write operations to avoid performance bottlenecks.

### Batch Writing

```php
use TimeSeriesPhp\Core\DataPoint;

// Create a batch of data points
$dataPoints = [];
for ($i = 0; $i < 1000; $i++) {
    $dataPoints[] = new DataPoint(
        'cpu_usage',
        ['value' => rand(0, 100)],
        ['host' => "server{$i % 10}", 'region' => 'us-west']
    );
}

// Write all data points in a single operation
$db->writeBatch($dataPoints);
```

### Chunked Batch Writing

For very large datasets, you may need to break them into smaller chunks:

```php
use TimeSeriesPhp\Core\DataPoint;

$dataPoints = [];
$batchSize = 1000;
$totalPoints = 10000;

for ($i = 0; $i < $totalPoints; $i++) {
    $dataPoints[] = new DataPoint(
        'cpu_usage',
        ['value' => rand(0, 100)],
        ['host' => "server{$i % 100}", 'region' => 'us-west']
    );
    
    // When we reach the batch size, write the batch and reset
    if (count($dataPoints) >= $batchSize) {
        $db->writeBatch($dataPoints);
        $dataPoints = []; // Clear the array
    }
}

// Write any remaining data points
if (!empty($dataPoints)) {
    $db->writeBatch($dataPoints);
}
```

### Asynchronous Writing

For non-blocking writes, you can use asynchronous processing:

```php
// Using a queue system (example with a simple array queue)
$queue = [];

// Add data points to the queue
$queue[] = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
$queue[] = new DataPoint('memory_usage', ['value' => 75.2], ['host' => 'server1']);

// In a background process or worker:
function processQueue(array $queue, TimeSeriesInterface $db) {
    $db->writeBatch($queue);
}

// Process the queue in a separate thread or process
// This is a simplified example; in a real application, you would use a proper queue system
```

### Write Buffering

```php
class BufferedTimeSeriesDB
{
    private $db;
    private $buffer = [];
    private $maxBufferSize;
    private $flushInterval;
    private $lastFlush;

    public function __construct(TimeSeriesInterface $db, int $maxBufferSize = 1000, int $flushInterval = 60)
    {
        $this->db = $db;
        $this->maxBufferSize = $maxBufferSize;
        $this->flushInterval = $flushInterval;
        $this->lastFlush = time();
    }

    public function write(DataPoint $dataPoint): bool
    {
        $this->buffer[] = $dataPoint;
        
        // Flush if buffer is full or flush interval has passed
        if (count($this->buffer) >= $this->maxBufferSize || time() - $this->lastFlush >= $this->flushInterval) {
            return $this->flush();
        }
        
        return true;
    }

    public function flush(): bool
    {
        if (empty($this->buffer)) {
            return true;
        }
        
        $success = $this->db->writeBatch($this->buffer);
        $this->buffer = [];
        $this->lastFlush = time();
        
        return $success;
    }

    public function __destruct()
    {
        // Ensure all data is written when the object is destroyed
        $this->flush();
    }
}

// Usage
$db = DriverManager::create('influxdb', $config);
$bufferedDb = new BufferedTimeSeriesDB($db, 1000, 60); // 1000 points or 60 seconds

// These writes will be buffered
$bufferedDb->write(new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']));
$bufferedDb->write(new DataPoint('memory_usage', ['value' => 75.2], ['host' => 'server1']));

// Manually flush if needed
$bufferedDb->flush();
```

## Optimizing Queries for Performance

Optimizing your queries can significantly improve performance, especially when dealing with large datasets.

### Use Time Ranges

Always specify a time range to limit the amount of data scanned:

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime()); // Only query the last hour
```

### Use Tags for Filtering

Tags are indexed, so filtering on tags is more efficient than filtering on fields:

```php
// Efficient: Filter on tags
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1') // 'host' is a tag
      ->timeRange(new DateTime('-1 hour'), new DateTime());

// Less efficient: Filter on fields
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('value', '>', 90) // 'value' is a field
      ->timeRange(new DateTime('-1 hour'), new DateTime());
```

### Use Aggregations

Use aggregations to reduce the amount of data returned:

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 day'), new DateTime())
      ->groupByTime('5m')           // Group by 5-minute intervals
      ->avg('value', 'avg_value');  // Calculate average
```

### Limit Results

Use limits to restrict the number of results:

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 day'), new DateTime())
      ->orderByTime('DESC')  // Most recent first
      ->limit(100);          // Only return 100 results
```

### Use Raw Queries for Complex Operations

For complex operations that can't be expressed using the query builder, use raw queries:

```php
// Example for InfluxDB using Flux
$flux = 'from(bucket: "your-bucket")
  |> range(start: -1h)
  |> filter(fn: (r) => r._measurement == "cpu_usage" and r.host == "server1")
  |> aggregateWindow(every: 5m, fn: mean)
  |> yield(name: "mean")';

$result = $db->rawQuery($flux);
```

## Migrating Between Database Backends

TimeSeriesPhp makes it easy to migrate between different time series database backends.

### Basic Migration

```php
use TimeSeriesPhp\Core\DriverManager;
use TimeSeriesPhp\Core\Query;

// Source database
$sourceConfig = new InfluxDBConfig([/* ... */]);
$sourceDb = DriverManager::create('influxdb', $sourceConfig);

// Target database
$targetConfig = new PrometheusConfig([/* ... */]);
$targetDb = DriverManager::create('prometheus', $targetConfig);

// Query data from the source database
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 day'), new DateTime());
$result = $sourceDb->query($query);

// Write data to the target database
$dataPoints = [];
foreach ($result as $row) {
    $dataPoints[] = new DataPoint(
        'cpu_usage',
        ['value' => $row['value']],
        ['host' => 'server1'],
        new DateTime($row['time'])
    );
}
$targetDb->writeBatch($dataPoints);
```

### Chunked Migration

For large datasets, migrate data in chunks:

```php
// Migrate data in 1-day chunks
$startDate = new DateTime('-30 days');
$endDate = new DateTime();
$chunkSize = 86400; // 1 day in seconds

while ($startDate < $endDate) {
    $chunkEnd = clone $startDate;
    $chunkEnd->modify("+{$chunkSize} seconds");
    
    if ($chunkEnd > $endDate) {
        $chunkEnd = $endDate;
    }
    
    // Query data for this chunk
    $query = new Query('cpu_usage');
    $query->select(['value'])
          ->where('host', '=', 'server1')
          ->timeRange($startDate, $chunkEnd);
    $result = $sourceDb->query($query);
    
    // Write data to the target database
    $dataPoints = [];
    foreach ($result as $row) {
        $dataPoints[] = new DataPoint(
            'cpu_usage',
            ['value' => $row['value']],
            ['host' => 'server1'],
            new DateTime($row['time'])
        );
    }
    $targetDb->writeBatch($dataPoints);
    
    // Move to the next chunk
    $startDate = $chunkEnd;
}
```

### Migration with Transformation

Sometimes you need to transform data during migration:

```php
// Query data from the source database
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 day'), new DateTime());
$result = $sourceDb->query($query);

// Transform and write data to the target database
$dataPoints = [];
foreach ($result as $row) {
    // Transform the data (e.g., convert units, rename tags)
    $value = $row['value'] * 100; // Convert from decimal to percentage
    
    $dataPoints[] = new DataPoint(
        'cpu_percentage', // Renamed measurement
        ['value' => $value],
        [
            'server' => 'server1', // Renamed tag
            'environment' => 'production' // Added tag
        ],
        new DateTime($row['time'])
    );
}
$targetDb->writeBatch($dataPoints);
```

## Working with Tags and Fields

Understanding when to use tags versus fields is crucial for optimal performance.

### Tag vs. Field Selection

```php
// Tags are indexed and used for filtering
// Fields are not indexed and are used for values

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

### Consistent Tag Usage

```php
// Consistent tag usage across measurements
$cpuDataPoint = new DataPoint(
    'cpu_usage',
    ['value' => 85.5],
    ['host' => 'server1', 'region' => 'us-west', 'environment' => 'production']
);

$memoryDataPoint = new DataPoint(
    'memory_usage',
    ['value' => 75.2],
    ['host' => 'server1', 'region' => 'us-west', 'environment' => 'production']
);

// This allows for consistent querying
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->where('environment', '=', 'production')
      ->timeRange(new DateTime('-1 hour'), new DateTime());
```

### Tag Cardinality

Be careful with high-cardinality tags (tags with many possible values):

```php
// Good: Low-cardinality tags
$dataPoint = new DataPoint(
    'http_requests',
    ['count' => 1],
    ['method' => 'GET', 'status' => '200', 'endpoint' => '/api/users']
);

// Bad: High-cardinality tags
$dataPoint = new DataPoint(
    'http_requests',
    ['count' => 1],
    ['user_id' => '12345', 'request_id' => 'abcdef123456', 'session_id' => '789012']
);
```

## Error Handling and Retry Strategies

Implementing robust error handling and retry strategies is essential for production applications.

### Basic Retry Strategy

```php
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\WriteException;
use TimeSeriesPhp\Exceptions\TSDBException;

function writeWithRetry(TimeSeriesInterface $db, DataPoint $dataPoint, int $maxRetries = 3, int $initialDelay = 1000) {
    $retries = 0;
    $delay = $initialDelay;
    
    while ($retries <= $maxRetries) {
        try {
            $success = $db->write($dataPoint);
            return $success;
        } catch (ConnectionException | WriteException $e) {
            // These exceptions are retryable
            $retries++;
            
            if ($retries > $maxRetries) {
                throw $e; // Max retries exceeded
            }
            
            // Exponential backoff
            usleep($delay * 1000); // Convert to microseconds
            $delay *= 2;
        } catch (TSDBException $e) {
            // Other exceptions are not retryable
            throw $e;
        }
    }
    
    return false;
}

// Usage
try {
    $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
    $success = writeWithRetry($db, $dataPoint);
} catch (TSDBException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Circuit Breaker Pattern

```php
class CircuitBreaker
{
    private $db;
    private $failureThreshold;
    private $resetTimeout;
    private $failures = 0;
    private $lastFailure = 0;
    private $state = 'CLOSED'; // CLOSED, OPEN, HALF_OPEN

    public function __construct(TimeSeriesInterface $db, int $failureThreshold = 3, int $resetTimeout = 30)
    {
        $this->db = $db;
        $this->failureThreshold = $failureThreshold;
        $this->resetTimeout = $resetTimeout;
    }

    public function write(DataPoint $dataPoint): bool
    {
        $this->checkState();
        
        if ($this->state === 'OPEN') {
            throw new \RuntimeException('Circuit is open, write operation rejected');
        }
        
        try {
            $success = $this->db->write($dataPoint);
            
            if ($this->state === 'HALF_OPEN') {
                $this->state = 'CLOSED';
                $this->failures = 0;
            }
            
            return $success;
        } catch (TSDBException $e) {
            $this->failures++;
            $this->lastFailure = time();
            
            if ($this->failures >= $this->failureThreshold) {
                $this->state = 'OPEN';
            }
            
            throw $e;
        }
    }

    private function checkState()
    {
        if ($this->state === 'OPEN' && time() - $this->lastFailure >= $this->resetTimeout) {
            $this->state = 'HALF_OPEN';
        }
    }
}

// Usage
$db = DriverManager::create('influxdb', $config);
$circuitBreaker = new CircuitBreaker($db, 3, 30);

try {
    $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']);
    $success = $circuitBreaker->write($dataPoint);
} catch (\RuntimeException $e) {
    echo "Circuit is open: " . $e->getMessage() . "\n";
} catch (TSDBException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
```

## Using with Laravel

TimeSeriesPhp provides Laravel integration for easy use in Laravel applications.

### Configuration

In your `config/app.php`, add the service provider:

```php
'providers' => [
    // ...
    TimeSeriesPhp\Support\Laravel\TimeSeriesServiceProvider::class,
],
```

And the facade:

```php
'aliases' => [
    // ...
    'TimeSeries' => TimeSeriesPhp\Support\Laravel\Facades\TimeSeries::class,
],
```

Create a configuration file `config/timeseries.php`:

```php
return [
    'default' => env('TIMESERIES_CONNECTION', 'influxdb'),
    
    'connections' => [
        'influxdb' => [
            'driver' => 'influxdb',
            'url' => env('INFLUXDB_URL', 'http://localhost:8086'),
            'token' => env('INFLUXDB_TOKEN'),
            'org' => env('INFLUXDB_ORG'),
            'bucket' => env('INFLUXDB_BUCKET'),
        ],
        
        'prometheus' => [
            'driver' => 'prometheus',
            'url' => env('PROMETHEUS_URL', 'http://localhost:9090'),
            'username' => env('PROMETHEUS_USERNAME'),
            'password' => env('PROMETHEUS_PASSWORD'),
        ],
        
        // Add other connections as needed
    ],
];
```

### Basic Usage with Laravel

```php
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;

// Using the facade
\TimeSeries::write(new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']));

$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new \DateTime('-1 hour'), new \DateTime());
$result = \TimeSeries::query($query);

// Using dependency injection
class MetricsController extends Controller
{
    public function store(Request $request, \TimeSeriesPhp\Contracts\TimeSeriesInterface $timeSeries)
    {
        $dataPoint = new DataPoint(
            'cpu_usage',
            ['value' => $request->input('value')],
            ['host' => $request->input('host')]
        );
        
        $success = $timeSeries->write($dataPoint);
        
        return response()->json(['success' => $success]);
    }
}
```

### Using Multiple Connections

```php
// Using a specific connection
$influxDb = \TimeSeries::connection('influxdb');
$prometheusDb = \TimeSeries::connection('prometheus');

$influxDb->write(new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']));
$prometheusDb->write(new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']));
```

### Laravel Middleware for Request Metrics

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Support\Laravel\Facades\TimeSeries;

class TrackRequestMetrics
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $duration = microtime(true) - $startTime;
        
        // Record request metrics
        $dataPoint = new DataPoint(
            'http_requests',
            ['duration' => $duration, 'status' => $response->getStatusCode()],
            [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $request->route() ? $request->route()->getName() : 'unknown',
            ]
        );
        
        TimeSeries::write($dataPoint);
        
        return $response;
    }
}
```
