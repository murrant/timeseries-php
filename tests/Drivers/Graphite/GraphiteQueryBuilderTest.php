<?php

namespace TimeSeriesPhp\Tests\Drivers\Graphite;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Drivers\Graphite\Query\GraphiteQueryBuilder;

class GraphiteQueryBuilderTest extends TestCase
{
    private GraphiteQueryBuilder $queryBuilder;

    private GraphiteQueryBuilder $queryBuilderWithPrefix;

    protected function setUp(): void
    {
        $this->queryBuilder = new GraphiteQueryBuilder;
        $this->queryBuilderWithPrefix = new GraphiteQueryBuilder('servers');
    }

    /**
     * Helper method to decode URL-encoded query string for assertions
     */
    private function decodeQueryString(string $queryString): string
    {
        return urldecode($queryString);
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.server1&from=1685314800&until=1685316540&format=json';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_prefix(): void
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=summarize(alias(averageSeries(cpu_usage.server1), "avg_value"), "5minute", "avg")&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        // Note: Graphite typically processes one aggregation at a time in the query builder
        $nativeQuery = 'target=group(alias(summarize(averageSeries(cpu_usage.server1), "10minute", "avg"), "avg_value"),alias(summarize(maxSeries(cpu_usage.server1), "10minute", "max"), "max_value"),alias(summarize(minSeries(cpu_usage.server1), "10minute", "min"), "min_value"))&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.server1&from=-1h&until=now&format=json';
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_multiple_fields(): void
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

    public function test_build_query_with_limit(): void
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

    public function test_build_query_with_ordering(): void
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

    public function test_build_query_with_regex_condition(): void
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.*&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.*&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.server1&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=summarize(alias(averageSeries(cpu_usage.server1), "avg_value"), "5minute", "avg")&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.server1&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=sortByMaxima(sortByMinima(cpu_usage.server1))&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.value&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.1&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=cpu_usage.2023-05-28T23:00:00+00:00&from=1685314800&until=1685316540&format=json';
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

        // Get the raw query string and decode it
        $queryString = $this->decodeQueryString($rawQuery->getRawQuery());

        // Assert the exact query string
        $nativeQuery = 'target=group(alias(summarize(averageSeries(group("cpu_usage.user", "cpu_usage.system")), "5minute", "avg"), "avg_user"),alias(summarize(maxSeries(group("cpu_usage.user", "cpu_usage.system")), "5minute", "max"), "max_system"))&from=1685314800&until=1685316540&format=json';
        $this->assertEquals($nativeQuery, $queryString);
    }
}
