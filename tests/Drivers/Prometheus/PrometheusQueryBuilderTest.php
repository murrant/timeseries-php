<?php

namespace TimeSeriesPhp\Tests\Drivers\Prometheus;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusQueryBuilder;

class PrometheusQueryBuilderTest extends TestCase
{
    private PrometheusQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = new PrometheusQueryBuilder();
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
        $nativeQuery = 'cpu_usage{host="server1"} # time range: 2023-05-28T23:00:00+00:00 to 2023-05-28T23:29:00+00:00';
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
        $nativeQuery = 'rate(avg(cpu_usage{host="server1"})[5m]) # time range: 2023-05-28T23:00:00+00:00 to 2023-05-28T23:29:00+00:00';
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
        $nativeQuery = 'rate(avg(cpu_usage{host="server1"})[10m]) # time range: 2023-05-28T23:00:00+00:00 to 2023-05-28T23:29:00+00:00';
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
              ->math('* 100', 'cpu_percentage');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = '(cpu_usage{host="server1"}) * 100 # time range: 2023-05-28T23:00:00+00:00 to 2023-05-28T23:29:00+00:00';
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
        $nativeQuery = 'cpu_usage{host="server1"} # relative time: 1h';
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
              ->groupBy(['host', 'cpu'])
              ->avg('value', 'avg_value');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'avg by (host,cpu) (cpu_usage{host="server1"}) # time range: 2023-05-28T23:00:00+00:00 to 2023-05-28T23:29:00+00:00';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function testBuildQueryWithRegexCondition(): void
    {
        // Create a query with regex condition
        $query = new Query('cpu_usage');
        $query->whereRegex('host', 'server.*')
              ->timeRange(
                  new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                  new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
              );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'cpu_usage{host=~"server.*"} # time range: 2023-05-28T23:00:00+00:00 to 2023-05-28T23:29:00+00:00';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function testBuildQueryWithInCondition(): void
    {
        // Create a query with IN condition
        $query = new Query('cpu_usage');
        $query->whereIn('host', ['server1', 'server2'])
              ->timeRange(
                  new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                  new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
              );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'cpu_usage{host=~"^(server1|server2)$"} # time range: 2023-05-28T23:00:00+00:00 to 2023-05-28T23:29:00+00:00';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function testBuildQueryWithPercentile(): void
    {
        // Create a query with percentile
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
              ->timeRange(
                  new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                  new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
              )
              ->percentile('value', 95, 'p95');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = 'quantile(0.95, cpu_usage{host="server1"}) # time range: 2023-05-28T23:00:00+00:00 to 2023-05-28T23:29:00+00:00';
        $this->assertEquals($nativeQuery, $queryString);
    }
}
