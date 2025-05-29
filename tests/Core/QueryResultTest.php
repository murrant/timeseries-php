<?php

namespace TimeSeriesPhp\Tests\Core;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Data\QueryResult;

class QueryResultTest extends TestCase
{
    public function test_constructor(): void
    {
        $series = [
            'value' => [
                ['date' => '2023-01-01T00:00:00Z', 'value' => 10],
                ['date' => '2023-01-01T01:00:00Z', 'value' => 15],
            ],
        ];
        $metadata = ['query_time' => 0.05, 'rows_returned' => 2];

        $result = new QueryResult($series, $metadata);

        $this->assertEquals($series, $result->getSeries());
        $this->assertEquals($metadata, $result->getMetadata());
    }

    public function test_constructor_with_defaults(): void
    {
        $result = new QueryResult;

        $this->assertEquals([], $result->getSeries());
        $this->assertEquals([], $result->getMetadata());
    }

    public function test_is_empty(): void
    {
        $emptyResult = new QueryResult;
        $this->assertTrue($emptyResult->isEmpty());

        $nonEmptyResult = new QueryResult([
            'value' => [
                ['date' => '2023-01-01T00:00:00Z', 'value' => 10],
            ],
        ]);
        $this->assertFalse($nonEmptyResult->isEmpty());
    }

    public function test_count(): void
    {
        $emptyResult = new QueryResult;
        $this->assertEquals(0, $emptyResult->count());

        $singleFieldResult = new QueryResult([
            'value' => [
                ['date' => '2023-01-01T00:00:00Z', 'value' => 10],
            ],
        ]);
        $this->assertEquals(1, $singleFieldResult->count());

        $multipleFieldResult = new QueryResult([
            'value' => [
                ['date' => '2023-01-01T00:00:00Z', 'value' => 10],
            ],
            'max' => [
                ['date' => '2023-01-01T00:00:00Z', 'value' => 20],
            ],
            'min' => [
                ['date' => '2023-01-01T00:00:00Z', 'value' => 5],
            ],
        ]);
        $this->assertEquals(3, $multipleFieldResult->count());
    }

    public function test_to_array(): void
    {
        $series = [
            'value' => [
                ['date' => '2023-01-01T00:00:00Z', 'value' => 10],
                ['date' => '2023-01-01T01:00:00Z', 'value' => 15],
            ],
        ];
        $metadata = ['query_time' => 0.05, 'rows_returned' => 2];

        $result = new QueryResult($series, $metadata);
        $array = $result->toArray();

        $this->assertArrayHasKey('series', $array);
        $this->assertArrayHasKey('metadata', $array);
        $this->assertEquals($series, $array['series']);
        $this->assertEquals($metadata, $array['metadata']);
    }

    public function test_append_point(): void
    {
        $result = new QueryResult;

        $result->appendPoint('2023-01-01T00:00:00Z', 'value', 10);
        $result->appendPoint('2023-01-01T01:00:00Z', 'value', 15);
        $result->appendPoint('2023-01-01T00:00:00Z', 'max', 20);

        $series = $result->getSeries();

        $this->assertArrayHasKey('value', $series);
        $this->assertArrayHasKey('max', $series);
        $this->assertCount(2, $series['value']);
        $this->assertCount(1, $series['max']);
        $this->assertEquals(10, $series['value'][0]['value']);
        $this->assertEquals(15, $series['value'][1]['value']);
        $this->assertEquals(20, $series['max'][0]['value']);
    }

    public function test_get_single_value(): void
    {
        // Test with specified field
        $result = new QueryResult([
            'value' => [
                ['date' => '2023-01-01T00:00:00Z', 'value' => 10],
            ],
            'max' => [
                ['date' => '2023-01-01T00:00:00Z', 'value' => 20],
            ],
        ]);

        $this->assertEquals(10, $result->getSingleValue('value'));
        $this->assertEquals(20, $result->getSingleValue('max'));

        // Test with default field (first one)
        $this->assertEquals(10, $result->getSingleValue());

        // Test with non-existent field
        $this->assertNull($result->getSingleValue('non_existent'));

        // Test with empty result
        $emptyResult = new QueryResult;
        $this->assertNull($emptyResult->getSingleValue());
    }
}
