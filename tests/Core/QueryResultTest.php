<?php

namespace TimeSeriesPhp\Tests\Core;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\QueryResult;

class QueryResultTest extends TestCase
{
    public function testConstructor()
    {
        $series = [
            ['time' => '2023-01-01T00:00:00Z', 'value' => 10],
            ['time' => '2023-01-01T01:00:00Z', 'value' => 15]
        ];
        $metadata = ['query_time' => 0.05, 'rows_returned' => 2];
        
        $result = new QueryResult($series, $metadata);
        
        $this->assertEquals($series, $result->getSeries());
        $this->assertEquals($metadata, $result->getMetadata());
    }
    
    public function testConstructorWithDefaults()
    {
        $result = new QueryResult();
        
        $this->assertEquals([], $result->getSeries());
        $this->assertEquals([], $result->getMetadata());
    }
    
    public function testIsEmpty()
    {
        $emptyResult = new QueryResult();
        $this->assertTrue($emptyResult->isEmpty());
        
        $nonEmptyResult = new QueryResult([['time' => '2023-01-01T00:00:00Z', 'value' => 10]]);
        $this->assertFalse($nonEmptyResult->isEmpty());
    }
    
    public function testCount()
    {
        $emptyResult = new QueryResult();
        $this->assertEquals(0, $emptyResult->count());
        
        $singleResult = new QueryResult([['time' => '2023-01-01T00:00:00Z', 'value' => 10]]);
        $this->assertEquals(1, $singleResult->count());
        
        $multipleResult = new QueryResult([
            ['time' => '2023-01-01T00:00:00Z', 'value' => 10],
            ['time' => '2023-01-01T01:00:00Z', 'value' => 15],
            ['time' => '2023-01-01T02:00:00Z', 'value' => 20]
        ]);
        $this->assertEquals(3, $multipleResult->count());
    }
    
    public function testToArray()
    {
        $series = [
            ['time' => '2023-01-01T00:00:00Z', 'value' => 10],
            ['time' => '2023-01-01T01:00:00Z', 'value' => 15]
        ];
        $metadata = ['query_time' => 0.05, 'rows_returned' => 2];
        
        $result = new QueryResult($series, $metadata);
        $array = $result->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('series', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertEquals($series, $array['series']);
        $this->assertEquals($metadata, $array['metadata']);
    }
}
