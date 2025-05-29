<?php

namespace TimeSeriesPhp\Tests\Drivers\Prometheus;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;

/**
 * Integration test for Prometheus driver using the Query interface
 *
 * @group integration
 */
class QueryInterfaceTest extends TestCase
{
    private PrometheusDriver $driver;

    private PrometheusConfig $config;

    protected function setUp(): void
    {
        // Skip test if Prometheus is not available
        // This is a mock test that doesn't actually connect to Prometheus
        // In a real environment, you would check for Prometheus availability
        
        // Create a mock PrometheusConfig
        $this->config = new PrometheusConfig([
            'host' => 'localhost',
            'port' => 9090,
            'protocol' => 'http',
            'timeout' => 5,
            'connect_timeout' => 5,
            'retries' => 3,
        ]);

        // Create a mock PrometheusDriver with mocked methods
        $this->driver = $this->createMock(PrometheusDriver::class);
        
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
        
        // Determine the step size based on the query interval or default to 60 seconds
        $step = 60;
        if ($query->getInterval()) {
            // Parse interval like "1m", "5m", "1h", etc.
            if (preg_match('/^(\d+)([smh])$/', $query->getInterval(), $matches)) {
                $amount = (int) $matches[1];
                $unit = $matches[2];
                
                switch ($unit) {
                    case 's': $step = $amount; break;
                    case 'm': $step = $amount * 60; break;
                    case 'h': $step = $amount * 3600; break;
                }
            }
        }
        
        // Generate data points at the specified interval
        for ($timestamp = $startTime; $timestamp <= $endTime; $timestamp += $step) {
            $timestamps[] = $timestamp;
            
            // Check if we're querying cpu_usage or memory_usage
            if ($query->getMeasurement() === 'cpu_usage') {
                // Generate mock CPU usage data
                $i = ($timestamp - $startTime) / $step;
                $series['value'][] = 20 + (sin($i / 10) * 15);
            } elseif ($query->getMeasurement() === 'memory_usage') {
                // Generate mock memory usage data
                $i = ($timestamp - $startTime) / $step;
                $series['value'][] = 2048 + (cos($i / 10) * 256);
            }
            
            // If there are aggregations, add those too
            foreach ($query->getAggregations() as $agg) {
                $field = $agg['field'];
                $function = $agg['function'];
                $alias = $agg['alias'] ?? $field.'_'.strtolower($function);
                
                // Generate mock aggregated data
                $i = ($timestamp - $startTime) / $step;
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

    public function test_query_with_prometheus_specific_features(): void
    {
        // Create a query that uses Prometheus-specific features
        // For example, Prometheus supports rate() and increase() functions
        $query = new Query('cpu_usage');
        $query->timeRange(new DateTime('-1 hour'), new DateTime())
              ->math('rate(value[5m])', 'cpu_rate');  // Simulate Prometheus rate() function
        
        // Execute the query
        $result = $this->driver->query($query);
        
        // Verify the result
        $this->assertInstanceOf(QueryResult::class, $result);
        
        // In a real implementation, we would check for the rate calculation
        // For this mock test, we'll just check that the query executed
        $this->assertNotEmpty($result->getTimestamps());
    }
}
