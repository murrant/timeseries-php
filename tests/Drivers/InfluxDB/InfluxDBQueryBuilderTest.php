<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBQueryBuilder;

class InfluxDBQueryBuilderTest extends TestCase
{
    private InfluxDBQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = new InfluxDBQueryBuilder('test_bucket');
    }

    public function test_build_simple_query(): void
    {
        // Create a simple query
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_aggregation(): void
    {
        // Create a query with aggregation
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->groupByTime('5m')  // Group by 5-minute intervals
            ->avg('value', 'avg_value');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> window(every: 5m)
  |> mean(column: "value")
  |> rename(columns: {_value: "avg_value"})';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_multiple_aggregations(): void
    {
        // Create a query with multiple aggregations
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->groupByTime('10m')  // Group by 10-minute intervals
            ->avg('value', 'avg_value')
            ->max('value', 'max_value')
            ->min('value', 'min_value');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> window(every: 10m)
  |> duplicate(column: "value", as: "value_copy1")
  |> duplicate(column: "value", as: "value_copy2")
  |> mean(column: "value")
  |> rename(columns: {_value: "avg_value"})
  |> max(column: "value_copy1")
  |> rename(columns: {_value: "max_value"})
  |> min(column: "value_copy2")
  |> rename(columns: {_value: "min_value"})';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_math_expression(): void
    {
        // Create a query with a math expression
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->math('r._value * 100', 'cpu_percentage');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> map(fn: (r) => ({ r with cpu_percentage: r._value * 100 }))';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_relative_time(): void
    {
        // Create a query with relative time
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->latest('1h');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: -1h)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_group_by(): void
    {
        // Create a query with group by
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->groupBy(['host', 'cpu']);

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> group(columns: ["host", "cpu"])';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_ordering_and_limit(): void
    {
        // Create a query with ordering and limit
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->orderByTime('DESC')
            ->limit(10);

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> sort(columns: ["time"], desc: true)
  |> limit(n: 10)';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_fill_policy(): void
    {
        // Create a query with fill policy
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->groupByTime('5m')
            ->avg('value', 'avg_value')
            ->fillValue(0);

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> window(every: 5m)
  |> mean(column: "value")
  |> rename(columns: {_value: "avg_value"})
  |> fill(value: 0)';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_not_in_condition(): void
    {
        // Create a query with NOT IN condition
        $query = new Query('cpu_usage');
        $query->whereNotIn('host', ['server3', 'server4'])
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] != "server3" and r["host"] != "server4")';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_between_condition(): void
    {
        // Create a query with BETWEEN condition
        $query = new Query('cpu_usage');
        $query->whereBetween('value', 50, 80)
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["value"] >= 50 and r["value"] <= 80)';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_timezone(): void
    {
        // Create a query with timezone
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->timezone('America/New_York');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> timeShift(duration: 0s, timeZone: "America/New_York")
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_having_clause(): void
    {
        // Create a query with having clause
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->groupByTime('5m')
            ->avg('value', 'avg_value')
            ->having('avg_value', '>', 50);

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> window(every: 5m)
  |> mean(column: "value")
  |> rename(columns: {_value: "avg_value"})
  |> filter(fn: (r) => r["avg_value"] > 50)';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_offset(): void
    {
        // Create a query with offset
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->offset(10);

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> tail(offset: 10)';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_multiple_ordering_fields(): void
    {
        // Create a query with multiple ordering fields
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->orderBy('host', 'ASC')
            ->orderBy('value', 'DESC');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> sort(columns: ["host"], desc: false)
  |> sort(columns: ["value"], desc: true)';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_distinct(): void
    {
        // Create a query with distinct
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->selectDistinct(['value']);

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> filter(fn: (r) => r._field == "value")
  |> distinct()';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_null_value(): void
    {
        // Create a query with null value
        $query = new Query('cpu_usage');
        $query->where('host', '=', null)
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == null)';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_boolean_value(): void
    {
        // Create a query with boolean value
        $query = new Query('cpu_usage');
        $query->where('active', '=', true)
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["active"] == true)';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_date_time_value(): void
    {
        // Create a query with DateTime value (converted to ISO 8601 string)
        $dateTime = new DateTime('@1685314800'); // 2023-05-28 23:00:00 UTC
        $dateTimeString = $dateTime->format('c'); // ISO 8601 format
        $query = new Query('cpu_usage');
        $query->where('timestamp', '=', $dateTimeString)
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["timestamp"] == "2023-05-28T23:00:00+00:00")';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_multiple_fields_and_different_aggregations(): void
    {
        // Create a query with multiple fields and different aggregations
        $query = new Query('cpu_usage');
        $query->select(['user', 'system'])
            ->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->groupByTime('5m')
            ->avg('user', 'avg_user')
            ->max('system', 'max_system');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'from(bucket: "test_bucket")
  |> range(start: 2023-05-28T23:00:00+00:00, stop: 2023-05-28T23:29:00+00:00)
  |> filter(fn: (r) => r._measurement == "cpu_usage")
  |> filter(fn: (r) => r["host"] == "server1")
  |> filter(fn: (r) => r._field == "user" or r._field == "system")
  |> window(every: 5m)
  |> duplicate(column: "user", as: "user_copy1")
  |> mean(column: "user")
  |> rename(columns: {_value: "avg_user"})
  |> max(column: "user_copy1")
  |> rename(columns: {_value: "max_system"})';
        $this->assertEquals($nativeQuery, $queryString);
    }
}
