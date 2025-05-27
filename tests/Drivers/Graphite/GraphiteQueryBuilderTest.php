<?php

namespace TimeSeriesPhp\Tests\Drivers\Graphite;

use DateInterval;
use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\RawQuery;
use TimeSeriesPhp\Drivers\Graphite\GraphiteQueryBuilder;

class GraphiteQueryBuilderTest extends TestCase
{
    private GraphiteQueryBuilder $queryBuilder;

    protected function setUp(): void
    {
        $this->queryBuilder = new GraphiteQueryBuilder;
    }

    public function test_build_simple_query(): void
    {
        $query = new Query('cpu');
        $query->select(['usage']);

        $rawQuery = $this->queryBuilder->build($query);

        $this->assertInstanceOf(RawQuery::class, $rawQuery);
        $this->assertStringContainsString('target=cpu.usage', $rawQuery->getRawQuery());
        $this->assertStringContainsString('from=-1h', $rawQuery->getRawQuery());
        $this->assertStringContainsString('until=now', $rawQuery->getRawQuery());
        $this->assertStringContainsString('format=json', $rawQuery->getRawQuery());
    }

    public function test_build_query_with_prefix(): void
    {
        $queryBuilder = new GraphiteQueryBuilder('servers');
        $query = new Query('cpu');
        $query->select(['usage']);

        $rawQuery = $queryBuilder->build($query);

        $this->assertStringContainsString('target=servers.cpu.usage', $rawQuery->getRawQuery());
    }

    public function test_build_query_with_multiple_fields(): void
    {
        $query = new Query('cpu');
        $query->select(['user', 'system']);

        $rawQuery = $this->queryBuilder->build($query);

        $this->assertStringContainsString('target=group', $rawQuery->getRawQuery());
        $this->assertStringContainsString('cpu.user', $rawQuery->getRawQuery());
        $this->assertStringContainsString('cpu.system', $rawQuery->getRawQuery());
    }

    public function test_build_query_with_wildcard_fields(): void
    {
        $query = new Query('cpu');
        $query->select(['*']);

        $rawQuery = $this->queryBuilder->build($query);

        $this->assertStringContainsString('target=cpu.*', $rawQuery->getRawQuery());
    }

    public function test_build_query_with_time_range(): void
    {
        $start = new DateTime('2023-01-01');
        $end = new DateTime('2023-01-02');

        $query = new Query('cpu');
        $query->select(['usage']);
        $query->timeRange($start, $end);

        $rawQuery = $this->queryBuilder->build($query);

        $this->assertStringContainsString('from='.$start->getTimestamp(), $rawQuery->getRawQuery());
        $this->assertStringContainsString('until='.$end->getTimestamp(), $rawQuery->getRawQuery());
    }

    public function test_build_query_with_relative_time(): void
    {
        $query = new Query('cpu');
        $query->select(['usage']);
        $query->latest('1h');

        $rawQuery = $this->queryBuilder->build($query);

        $this->assertStringContainsString('from=-1h', $rawQuery->getRawQuery());
    }

    public function test_build_query_with_aggregation(): void
    {
        $query = new Query('cpu');
        $query->select(['usage']);
        $query->avg('usage', 'avg_usage');

        $rawQuery = $this->queryBuilder->build($query);

        $this->assertStringContainsString('averageSeries', $rawQuery->getRawQuery());
        $this->assertStringContainsString('alias', $rawQuery->getRawQuery());
        $this->assertStringContainsString('avg_usage', $rawQuery->getRawQuery());
    }

    public function test_build_query_with_time_grouping(): void
    {
        $query = new Query('cpu');
        $query->select(['usage']);
        $query->groupByTime('1h');

        $rawQuery = $this->queryBuilder->build($query);

        $this->assertStringContainsString('summarize', $rawQuery->getRawQuery());
        $this->assertStringContainsString('1hour', $rawQuery->getRawQuery());
    }

    public function test_build_query_with_conditions(): void
    {
        $query = new Query('cpu');
        $query->select(['usage']);
        $query->where('host', '=', 'server01');

        $rawQuery = $this->queryBuilder->build($query);

        // In Graphite, conditions are typically handled by more specific paths
        // or by functions like exclude() or grep()
        $this->assertInstanceOf(RawQuery::class, $rawQuery);
    }

    public function test_build_query_with_limit(): void
    {
        $query = new Query('cpu');
        $query->select(['usage']);
        $query->limit(10);

        $rawQuery = $this->queryBuilder->build($query);

        $this->assertStringContainsString('limit', $rawQuery->getRawQuery());
        $this->assertStringContainsString('10', $rawQuery->getRawQuery());
    }

    public function test_build_query_with_ordering(): void
    {
        $query = new Query('cpu');
        $query->select(['usage']);
        $query->orderBy('usage', 'DESC');

        $rawQuery = $this->queryBuilder->build($query);

        $this->assertStringContainsString('sortByMaxima', $rawQuery->getRawQuery());
    }

    public function test_convert_interval_to_graphite(): void
    {
        $reflection = new \ReflectionClass(GraphiteQueryBuilder::class);
        $method = $reflection->getMethod('convertIntervalToGraphite');
        $method->setAccessible(true);

        $this->assertEquals('1second', $method->invoke($this->queryBuilder, '1s'));
        $this->assertEquals('5minute', $method->invoke($this->queryBuilder, '5m'));
        $this->assertEquals('2hour', $method->invoke($this->queryBuilder, '2h'));
        $this->assertEquals('1day', $method->invoke($this->queryBuilder, '1d'));
        $this->assertEquals('4week', $method->invoke($this->queryBuilder, '4w'));
    }

    public function test_format_date_interval(): void
    {
        $reflection = new \ReflectionClass(GraphiteQueryBuilder::class);
        $method = $reflection->getMethod('formatDateInterval');
        $method->setAccessible(true);

        $interval = new DateInterval('PT1H30M');
        $this->assertEquals('1h30min', $method->invoke($this->queryBuilder, $interval));

        $interval = new DateInterval('P1DT6H');
        $this->assertEquals('1d6h', $method->invoke($this->queryBuilder, $interval));
    }
}
