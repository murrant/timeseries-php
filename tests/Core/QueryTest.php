<?php

namespace TimeSeriesPhp\Tests\Core;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query;

class QueryTest extends TestCase
{
    public function test_constructor()
    {
        $query = new Query('cpu_usage');
        $this->assertEquals('cpu_usage', $query->getMeasurement());
        $this->assertEquals(['*'], $query->getFields());
        $this->assertEquals([], $query->getTags());
        $this->assertNull($query->getStartTime());
        $this->assertNull($query->getEndTime());
        $this->assertEquals([], $query->getGroupBy());
        $this->assertNull($query->getAggregation());
        $this->assertNull($query->getInterval());
        $this->assertNull($query->getLimit());
        $this->assertEquals([], $query->getOrderBy());
    }

    public function test_select()
    {
        $query = new Query('cpu_usage');
        $result = $query->select(['usage_user', 'usage_system']);

        $this->assertSame($query, $result, 'Method should return $this for chaining');
        $this->assertEquals(['usage_user', 'usage_system'], $query->getFields());
    }

    public function test_where()
    {
        $query = new Query('cpu_usage');
        $result = $query->where('host', 'server01');

        $this->assertSame($query, $result, 'Method should return $this for chaining');
        $this->assertEquals(['host' => 'server01'], $query->getTags());

        // Test multiple where clauses
        $query->where('region', 'us-west');
        $this->assertEquals(['host' => 'server01', 'region' => 'us-west'], $query->getTags());
    }

    public function test_time_range()
    {
        $start = new DateTime('2023-01-01');
        $end = new DateTime('2023-01-02');

        $query = new Query('cpu_usage');
        $result = $query->timeRange($start, $end);

        $this->assertSame($query, $result, 'Method should return $this for chaining');
        $this->assertSame($start, $query->getStartTime());
        $this->assertSame($end, $query->getEndTime());
    }

    public function test_group_by()
    {
        $query = new Query('cpu_usage');
        $result = $query->groupBy(['host', 'region']);

        $this->assertSame($query, $result, 'Method should return $this for chaining');
        $this->assertEquals(['host', 'region'], $query->getGroupBy());
    }

    public function test_aggregate()
    {
        $query = new Query('cpu_usage');
        $result = $query->aggregate('mean', '1h');

        $this->assertSame($query, $result, 'Method should return $this for chaining');
        $this->assertEquals('mean', $query->getAggregation());
        $this->assertEquals('1h', $query->getInterval());

        // Test without interval
        $query = new Query('cpu_usage');
        $query->aggregate('max');
        $this->assertEquals('max', $query->getAggregation());
        $this->assertNull($query->getInterval());
    }

    public function test_limit()
    {
        $query = new Query('cpu_usage');
        $result = $query->limit(100);

        $this->assertSame($query, $result, 'Method should return $this for chaining');
        $this->assertEquals(100, $query->getLimit());
    }

    public function test_order_by()
    {
        $query = new Query('cpu_usage');
        $result = $query->orderBy('time', 'DESC');

        $this->assertSame($query, $result, 'Method should return $this for chaining');
        $this->assertEquals(['time' => 'DESC'], $query->getOrderBy());

        // Test default direction
        $query = new Query('cpu_usage');
        $query->orderBy('value');
        $this->assertEquals(['value' => 'ASC'], $query->getOrderBy());

        // Test multiple order by clauses
        $query->orderBy('time', 'DESC');
        $this->assertEquals(['value' => 'ASC', 'time' => 'DESC'], $query->getOrderBy());
    }

    public function test_method_chaining()
    {
        $start = new DateTime('2023-01-01');
        $end = new DateTime('2023-01-02');

        $query = new Query('cpu_usage');
        $query->select(['usage_user', 'usage_system'])
            ->where('host', 'server01')
            ->where('region', 'us-west')
            ->timeRange($start, $end)
            ->groupBy(['host'])
            ->aggregate('mean', '5m')
            ->limit(100)
            ->orderBy('time', 'DESC');

        $this->assertEquals('cpu_usage', $query->getMeasurement());
        $this->assertEquals(['usage_user', 'usage_system'], $query->getFields());
        $this->assertEquals(['host' => 'server01', 'region' => 'us-west'], $query->getTags());
        $this->assertSame($start, $query->getStartTime());
        $this->assertSame($end, $query->getEndTime());
        $this->assertEquals(['host'], $query->getGroupBy());
        $this->assertEquals('mean', $query->getAggregation());
        $this->assertEquals('5m', $query->getInterval());
        $this->assertEquals(100, $query->getLimit());
        $this->assertEquals(['time' => 'DESC'], $query->getOrderBy());
    }
}
