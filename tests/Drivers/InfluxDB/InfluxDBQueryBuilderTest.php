<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBQueryBuilder;

class InfluxDBQueryBuilderTest extends TestCase
{
    private InfluxDBQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = new InfluxDBQueryBuilder('test_bucket');
    }

    public function testBuildSimpleQuery(): void
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

    public function testBuildQueryWithAggregation(): void
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

    public function testBuildQueryWithMultipleAggregations(): void
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

    public function testBuildQueryWithMathExpression(): void
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

    public function testBuildQueryWithRelativeTime(): void
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

    public function testBuildQueryWithGroupBy(): void
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

    public function testBuildQueryWithOrderingAndLimit(): void
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

    public function testBuildQueryWithFillPolicy(): void
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
}
