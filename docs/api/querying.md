# Building and Executing Queries in TimeSeriesPhp

This document provides detailed information about building and executing queries in TimeSeriesPhp.

## Introduction

TimeSeriesPhp provides a powerful and flexible query builder that allows you to construct complex queries using a fluent interface. The query builder abstracts the differences between various time series database query languages, allowing you to write code that works across different database backends.

## The Query Class

The `Query` class is the main entry point for building queries in TimeSeriesPhp.

### Creating a Query

```php
__construct(string $measurement)
```

Creates a new query for the specified measurement.

#### Parameters

- `$measurement` (string): The name of the measurement to query

#### Examples

```php
// Create a query for the 'cpu_usage' measurement
$query = new Query('cpu_usage');
```

## Basic Query Methods

### Selecting Fields

```php
select(array $fields): self
```

Specifies which fields to select in the query.

#### Parameters

- `$fields` (array): An array of field names to select

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
$query = new Query('cpu_usage');
$query->select(['value', 'usage_user', 'usage_system']);
```

### Filtering Data

```php
where(string $field, string $operator, mixed $value): self
orWhere(string $field, string $operator, mixed $value): self
```

Adds a condition to filter the query results.

#### Parameters

- `$field` (string): The field name to filter on
- `$operator` (string): The comparison operator (e.g., '=', '>', '<', '!=')
- `$value` (mixed): The value to compare against

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->orWhere('host', '=', 'server2');
```

### Advanced Filtering

```php
whereIn(string $field, array $values): self
whereNotIn(string $field, array $values): self
whereBetween(string $field, mixed $min, mixed $max): self
whereRegex(string $field, string $pattern): self
```

Adds advanced filtering conditions to the query.

#### Parameters

- `whereIn` / `whereNotIn`:
  - `$field` (string): The field name to filter on
  - `$values` (array): An array of values to include/exclude
- `whereBetween`:
  - `$field` (string): The field name to filter on
  - `$min` (mixed): The minimum value
  - `$max` (mixed): The maximum value
- `whereRegex`:
  - `$field` (string): The field name to filter on
  - `$pattern` (string): The regular expression pattern

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->whereIn('host', ['server1', 'server2', 'server3'])
      ->whereNotIn('region', ['us-east', 'eu-west'])
      ->whereBetween('value', 10, 90)
      ->whereRegex('host', '^web-.*');
```

## Time Range Methods

### Setting a Time Range

```php
timeRange(?DateTime $start, ?DateTime $end): self
```

Specifies the time range for the query.

#### Parameters

- `$start` (DateTime, nullable): The start time of the range
- `$end` (DateTime, nullable): The end time of the range

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->timeRange(new DateTime('-1 hour'), new DateTime());
```

### Since and Until

```php
since(DateTime $start): self
until(DateTime $end): self
```

Specifies the start or end time for the query.

#### Parameters

- `since`: `$start` (DateTime): The start time
- `until`: `$end` (DateTime): The end time

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->since(new DateTime('-1 day'))
      ->until(new DateTime());
```

### Latest Data

```php
latest(string $duration): self
```

Specifies to query the latest data within the given duration.

#### Parameters

- `$duration` (string): The duration (e.g., '1h', '30m', '1d')

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->latest('1h'); // Last hour
```

## Aggregation Methods

### Grouping

```php
groupByTime(string $interval): self
groupBy(array $tags, ?string $interval = null): self
```

Groups the results by time or tags.

#### Parameters

- `groupByTime`:
  - `$interval` (string): The time interval to group by (e.g., '5m', '1h')
- `groupBy`:
  - `$tags` (array): An array of tag names to group by
  - `$interval` (string, optional): The time interval to group by

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
// Group by time only
$query = new Query('cpu_usage');
$query->select(['value'])
      ->groupByTime('5m');

// Group by tags and time
$query = new Query('cpu_usage');
$query->select(['value'])
      ->groupBy(['host', 'region'], '5m');
```

### Aggregation Functions

```php
sum(string $field, ?string $alias = null): self
avg(string $field, ?string $alias = null): self
count(string $field, ?string $alias = null): self
min(string $field, ?string $alias = null): self
max(string $field, ?string $alias = null): self
first(string $field, ?string $alias = null): self
last(string $field, ?string $alias = null): self
percentile(string $field, float $percentile, ?string $alias = null): self
stddev(string $field, ?string $alias = null): self
```

Applies an aggregation function to a field.

#### Parameters

- `$field` (string): The field to aggregate
- `$alias` (string, optional): An alias for the result
- `percentile` only: `$percentile` (float): The percentile to calculate (0-100)

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->groupByTime('5m')
      ->avg('value', 'avg_value')
      ->max('value', 'max_value')
      ->min('value', 'min_value')
      ->percentile('value', 95, 'p95');
```

## Fill Methods

```php
fillNull(): self
fillNone(): self
fillPrevious(): self
fillLinear(): self
fillValue(mixed $value): self
```

Specifies how to fill missing values in grouped results.

#### Parameters

- `fillValue`: `$value` (mixed): The value to use for filling

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->groupByTime('5m')
      ->avg('value', 'avg_value')
      ->fillNull(); // Fill missing values with null

$query = new Query('cpu_usage');
$query->select(['value'])
      ->groupByTime('5m')
      ->avg('value', 'avg_value')
      ->fillPrevious(); // Fill missing values with the previous value

$query = new Query('cpu_usage');
$query->select(['value'])
      ->groupByTime('5m')
      ->avg('value', 'avg_value')
      ->fillValue(0); // Fill missing values with 0
```

## Ordering and Limiting

```php
orderByTime(string $direction = 'ASC'): self
limit(int $limit): self
offset(int $offset): self
```

Controls the order and number of results.

#### Parameters

- `orderByTime`: `$direction` (string): The direction ('ASC' or 'DESC')
- `limit`: `$limit` (int): The maximum number of results to return
- `offset`: `$offset` (int): The number of results to skip

#### Returns

- `self`: The query instance (for method chaining)

#### Examples

```php
$query = new Query('cpu_usage');
$query->select(['value'])
      ->orderByTime('DESC') // Most recent first
      ->limit(100)          // Only return 100 results
      ->offset(50);         // Skip the first 50 results
```

## Executing Queries

Once you have built a query, you can execute it using the `query()` method of a database driver instance.

```php
$db = DriverManager::create('influxdb', $config);
$query = new Query('cpu_usage');
$query->select(['value'])
      ->where('host', '=', 'server1')
      ->timeRange(new DateTime('-1 hour'), new DateTime());
$result = $db->query($query);
```

### Processing Query Results

The `query()` method returns a `QueryResult` object, which implements the `\Iterator` interface, allowing you to iterate over the results:

```php
foreach ($result as $row) {
    echo "Time: {$row['time']}, Value: {$row['value']}\n";
}
```

You can also access the results as an array:

```php
$rows = $result->toArray();
foreach ($rows as $row) {
    echo "Time: {$row['time']}, Value: {$row['value']}\n";
}
```

## Raw Queries

If you need to execute a database-specific query that cannot be expressed using the query builder, you can use the `rawQuery()` method:

```php
$db = DriverManager::create('influxdb', $config);
$result = $db->rawQuery('SELECT mean("value") FROM "cpu_usage" WHERE time > now() - 1h GROUP BY time(5m)');
```

Note that raw queries are not portable across different database backends.

## Best Practices

### Query Optimization

- **Use time ranges**: Always specify a time range to limit the amount of data scanned.
- **Use tags for filtering**: Tags are indexed, so filtering on tags is more efficient than filtering on fields.
- **Group by time**: When querying large time ranges, group by time to reduce the amount of data returned.
- **Use aggregations**: Use aggregation functions to pre-process data on the server side.

### Memory Management

When working with large result sets, be mindful of memory usage:

- Use limits to restrict the number of results
- Process results in batches
- Consider using aggregations to reduce the amount of data returned

### Driver-Specific Considerations

Different time series database drivers may have specific optimizations or limitations:

#### InfluxDB

- InfluxQL and Flux have different capabilities and syntax
- Some complex queries may require using raw queries with Flux

#### Prometheus

- Prometheus has a powerful query language (PromQL) but with different semantics
- Some advanced PromQL features may require using raw queries

#### Graphite

- Graphite's query language is function-based
- Some complex transformations may require using raw queries

#### RRDtool

- RRDtool has limited query capabilities
- Time ranges must align with the RRD's step size
