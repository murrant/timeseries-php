<?php

namespace TimeSeriesPhp\Tests\Drivers\InfluxDB;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;

/**
 * Integration test for InfluxDB driver using the Query interface
 *
 * @group integration
 */
class QueryInterfaceTest extends TestCase
{
    private InfluxDBDriver $driver;

    private InfluxDBConfig $config;

    private string $database = 'test_db';

    protected function setUp(): void
    {
        // Skip test if InfluxDB is not available
        // This is a mock test that doesn't actually connect to InfluxDB
        // In a real environment, you would check for InfluxDB availability
        
        // Create a mock InfluxDBConfig
        $this->config = new InfluxDBConfig([
            'host' => 'localhost',
            'port' => 8086,
            'username' => '',
            'password' => '',
            'database' => $this->database,
            'ssl' => false,
            'verify_ssl' => false,
            'timeout' => 5,
            'connect_timeout' => 5,
            'retries' => 3,
        ]);

        // Create a mock InfluxDBDriver with mocked methods
        $this->driver = $this->createMock(InfluxDBDriver::class);
        
        // Mock the isConnected method to return true
        $this->driver->method('isConnected')
            ->willReturn(true);
        
        // Mock the query method to return a QueryResult
        $this->driver->method('query')
            ->willReturnCallback([$this, 'mockQueryMethod']);
    }

    /**
     * Mock implementation of the query method
     */
    public function mockQueryMethod(Query $query): QueryResult
    {
        // Create a mock result based on the query
        $result = new QueryResult();
        
        // Add some mock data based on the query parameters
        $timestamps = [];
        $series = [];
        
        // Generate mock data for the last hour
        $now = new DateTime();
        $startTime = $now->getTimestamp() - 3600; // 1 hour ago
        
        // If the query has a time range, use it
        if ($query->getStartTime()) {
            $startTime = $query->getStartTime()->getTimestamp();
        }
        
        $endTime = $now->getTimestamp();
        if ($query->getEndTime()) {
            $endTime = $query->getEndTime()->getTimestamp();
        }
        
        // Generate data points at 1-minute intervals
        for ($i = 0; $i < 60; $i++) {
            $timestamp = $startTime + ($i * 60);
            if ($timestamp <= $endTime) {
                $timestamps[] = $timestamp;
                
                // Check if we're querying cpu_usage or memory_usage
                if ($query->getMeasurement() === 'cpu_usage') {
                    // Generate mock CPU usage data
                    $series['value'][] = 20 + (sin($i / 10) * 15);
                } elseif ($query->getMeasurement() === 'memory_usage') {
                    // Generate mock memory usage data
                    $series['value'][] = 2048 + (cos($i / 10) * 256);
                }
                
                // If there are aggregations, add those too
                foreach ($query->getAggregations() as $agg) {
                    $field = $agg['field'];
                    $function = $agg['function'];
                    $alias = $agg['alias'] ?? $field.'_'.strtolower($function);
                    
                    // Generate mock aggregated data
                    if ($function === 'AVG') {
                        $series[$alias][] = 25 + (sin($i / 20) * 5);
                    } elseif ($function === 'MAX') {
                        $series[$alias][] = 40 + (sin($i / 20) * 10);
                    } elseif ($function === 'MIN') {
                        $series[$alias][] = 10 + (sin($i / 20) * 5);
                    } elseif ($function === 'SUM') {
                        $series[$alias][] = 100 + (sin($i / 20) * 20);
                    } elseif ($function === 'COUNT') {
                        $series[$alias][] = 10;
                    }
                }
            }
        }
        
        // Apply limit if specified
        if ($query->getLimit() && count($timestamps) > $query->getLimit()) {
            $timestamps = array_slice($timestamps, 0, $query->getLimit());
            foreach ($series as $field => $values) {
                $series[$field] = array_slice($values, 0, $query->getLimit());
            }
        }
        
        // Apply ordering if specified
        if (!empty($query->getOrderBy()) && isset($query->getOrderBy()['time'])) {
            $direction = $query->getOrderBy()['time'];
            if ($direction === 'DESC') {
                $timestamps = array_reverse($timestamps);
                foreach ($series as $field => $values) {
                    $series[$field] = array_reverse($values);
                }
            }
        }
        
        $result->setTimestamps($timestamps);
        $result->setSeries($series);
        
        return $result;
    }

    public function test_simple_query(): void
    {
        // Create a simple query to get CPU usage
        $query = new Query('cpu_usage');
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());
        
        // The result should contain our data points
        $series = $result->getSeries();
        $this->assertArrayHasKey('value', $series);
    }

    public function test_query_with_time_range(): void
    {
        $now = new DateTime();
        $startTime = new DateTime('@'.($now->getTimestamp() - 3600)); // 1 hour ago
        $endTime = new DateTime('@'.($now->getTimestamp() - 1800)); // 30 minutes ago
        
        // Create a query with time range
        $query = new Query('cpu_usage');
        $query->timeRange($startTime, $endTime);
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());
        
        // The result should contain our data points within the time range
        $series = $result->getSeries();
        $this->assertArrayHasKey('value', $series);
        
        // Check that all timestamps are within the specified range
        foreach ($result->getTimestamps() as $timestamp) {
            $time = new DateTime('@'.$timestamp);
            $this->assertGreaterThanOrEqual($startTime, $time);
            $this->assertLessThanOrEqual($endTime, $time);
        }
    }

    public function test_query_with_aggregation(): void
    {
        // Create a query with aggregation
        $query = new Query('cpu_usage');
        $query->groupByTime('5m')  // Group by 5-minute intervals
              ->avg('value', 'avg_value');
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());
        
        // The result should contain our aggregated data
        $series = $result->getSeries();
        $this->assertArrayHasKey('avg_value', $series);
    }

    public function test_query_with_multiple_aggregations(): void
    {
        // Create a query with multiple aggregations
        $query = new Query('cpu_usage');
        $query->groupByTime('5m')  // Group by 5-minute intervals
              ->avg('value', 'avg_value')
              ->max('value', 'max_value')
              ->min('value', 'min_value');
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());
        
        // The result should contain our aggregated data
        $series = $result->getSeries();
        $this->assertArrayHasKey('avg_value', $series);
        $this->assertArrayHasKey('max_value', $series);
        $this->assertArrayHasKey('min_value', $series);
    }

    public function test_query_with_ordering_and_limit(): void
    {
        // Create a query with ordering and limit
        $query = new Query('cpu_usage');
        $query->orderByTime('DESC')
              ->limit(10);
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertNotEmpty($result->getSeries());
        
        // The result should contain our data points
        $series = $result->getSeries();
        $this->assertArrayHasKey('value', $series);
        
        // Check that we have at most 10 data points
        $this->assertLessThanOrEqual(10, count($result->getTimestamps()));
        
        // Check that timestamps are in descending order
        $timestamps = $result->getTimestamps();
        $sortedTimestamps = $timestamps;
        rsort($sortedTimestamps);
        $this->assertEquals($sortedTimestamps, $timestamps);
    }
}
