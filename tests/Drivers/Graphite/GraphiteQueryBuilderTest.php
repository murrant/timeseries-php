<?php

namespace TimeSeriesPhp\Tests\Drivers\Graphite;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\Graphite\GraphiteQueryBuilder;

class GraphiteQueryBuilderTest extends TestCase
{
    private GraphiteQueryBuilder $queryBuilder;
    private GraphiteQueryBuilder $queryBuilderWithPrefix;

    protected function setUp(): void
    {
        $this->queryBuilder = new GraphiteQueryBuilder();
        $this->queryBuilderWithPrefix = new GraphiteQueryBuilder('servers');
    }

    /**
     * Helper method to decode URL-encoded query string for assertions
     */
    private function decodeQueryString(string $queryString): string
    {
        return urldecode($queryString);
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.server1&from=1685314800&until=1685316540&format=json';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function testBuildQueryWithPrefix(): void
    {
        // Create a simple query
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
              ->timeRange(
                  new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                  new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
              );

        // Build the query with prefix
        $rawQuery = $this->queryBuilderWithPrefix->build($query);

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=servers.cpu_usage.server1&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=summarize(alias(averageSeries(cpu_usage.server1), "avg_value"), "5minute", "avg")&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        // Note: Graphite typically processes one aggregation at a time in the query builder
        $nativeQuery = 'target=group(alias(summarize(averageSeries(cpu_usage.server1), "10minute", "avg"), "avg_value"),alias(summarize(maxSeries(cpu_usage.server1), "10minute", "max"), "max_value"),alias(summarize(minSeries(cpu_usage.server1), "10minute", "min"), "min_value"))&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.server1&from=-1h&until=now&format=json';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function testBuildQueryWithMultipleFields(): void
    {
        // Create a query with multiple fields
        $query = new Query('cpu_usage');
        $query->select(['user', 'system'])
              ->timeRange(
                  new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                  new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
              );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=group("cpu_usage.user", "cpu_usage.system")&from=1685314800&until=1685316540&format=json';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function testBuildQueryWithLimit(): void
    {
        // Create a query with limit
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
              ->timeRange(
                  new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                  new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
              )
              ->limit(10);

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=limit(cpu_usage.server1, 10)&from=1685314800&until=1685316540&format=json';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function testBuildQueryWithOrdering(): void
    {
        // Create a query with ordering
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
              ->timeRange(
                  new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                  new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
              )
              ->orderByTime('DESC');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=sortByMaxima(cpu_usage.server1)&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=grep(cpu_usage.*, "server.*")&from=1685314800&until=1685316540&format=json';
        $this->assertEquals($nativeQuery, $queryString);
    }
}
