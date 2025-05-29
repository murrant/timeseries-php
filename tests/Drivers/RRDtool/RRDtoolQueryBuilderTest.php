<?php

namespace TimeSeriesPhp\Tests\Drivers\RRDtool;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolQueryBuilder;
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' '--step' '300' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'CDEF:agg1000=v1,v2,+,2,/' 'XPORT:agg1000:avg_value'";
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
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' '--step' '600' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'CDEF:agg1000=v1,v2,+,2,/' 'CDEF:agg1001=v1,v2,MAX' 'CDEF:agg1002=v1,v2,MIN' 'XPORT:agg1000:avg_value' 'XPORT:agg1001:max_value' 'XPORT:agg1002:min_value'";
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
              ->math('value * 100', 'cpu_percentage');

        // Build the query
        $rawQuery = $this->queryBuilder->build($query);

        // Get the raw query string
        $queryString = $rawQuery->getRawQuery();

        // Assert the exact query string
        $nativeQuery = "'xport' '--json' '--start' '1685314800' '--end' '1685316540' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'CDEF:math2000=value,*,100' 'XPORT:math2000:cpu_percentage'";
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
        $nativeQuery = "'xport' '--json' '--start' 'end-3600s' 'DEF:v1=/path/to/rrd/cpu_usage_host-server1.rrd:value:AVERAGE' 'XPORT:v1:value'";
        $this->assertEquals($nativeQuery, $queryString);
    }
}
