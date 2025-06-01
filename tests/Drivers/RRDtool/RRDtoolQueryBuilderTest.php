<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Drivers\RRDtool\Query\RRDtoolQueryBuilder;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;

class RRDtoolQueryBuilderTest extends TestCase
{
    private RRDtoolQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        // Create a mock for RRDTagStrategyInterface
        $tagStrategy = $this->createMock(RRDTagStrategyInterface::class);

        // Configure the mock to return a predefined list of RRD file paths
        $tagStrategy->method('resolveFilePaths')
            ->willReturn(['/path/to/rrd/cpu_usage_host-server1.rrd']);

        $this->queryBuilder = new RRDtoolQueryBuilder($tagStrategy);
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' '--step' '300' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'CDEF:agg1000=v1,v2,+,2,/' 'XPORT:agg1000:avg_value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' '--step' '600' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'CDEF:agg1000=v1,v2,+,2,/' 'CDEF:agg1001=v1,v2,MAX' 'CDEF:agg1002=v1,v2,MIN' 'XPORT:agg1000:avg_value' 'XPORT:agg1001:max_value' 'XPORT:agg1002:min_value'";
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
            ->math('value * 100', 'cpu_percentage');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'CDEF:math2000=value,*,100' 'XPORT:math2000:cpu_percentage'";
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
        $nativeQuery = "'xport' '--json' '--start' 'end-3600s' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_in_condition(): void
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_percentile(): void
    {
        // Create a query with percentile
        $query = new Query('cpu_usage');
        $query->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            )
            ->groupByTime('5m')  // Group by 5-minute intervals (required for aggregations)
            ->percentile('value', 95, 'p95');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' '--step' '300' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'VDEF:agg1000=v1,95,PERCENT' 'XPORT:agg1000:p95'";
        $this->assertEquals($nativeQuery, $queryString);
    }

    public function test_build_query_with_multiple_fields(): void
    {
        // Create a query with multiple fields
        $query = new Query('cpu_usage');
        $query->select(['user', 'system'])
            ->where('host', '=', 'server1')
            ->timeRange(
                new DateTime('@1685314800'), // 2023-05-28 23:00:00 UTC
                new DateTime('@1685316540')  // 2023-05-28 23:29:00 UTC
            );

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:user:AVERAGE' 'DEF:v2=/path/to/rrd/cpu_usage_host-server1.rrd:system:AVERAGE' 'XPORT:v1:user' 'XPORT:v2:system'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' '--step' '300' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'CDEF:agg1000=v1,v2,+,2,/' 'XPORT:agg1000:avg_value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' '--step' '300' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:user:AVERAGE' 'DEF:v2=/path/to/rrd/cpu_usage_host-server1.rrd:system:AVERAGE' 'CDEF:agg1000=v1,v2,+,2,/' 'CDEF:agg1001=v1,v2,MAX' 'XPORT:agg1000:avg_user' 'XPORT:agg1001:max_system'";
        $this->assertEquals($nativeQuery, $queryString);
    }
}
