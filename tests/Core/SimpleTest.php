<?php

namespace TimeSeriesPhp\Tests\Core;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\DataPoint;

class SimpleTest extends TestCase
{
    public function test_data_point_creation(): void
    {
        // Create a simple data point
        $dataPoint = new DataPoint('cpu_usage', ['value' => 85.5]);

        // Verify the measurement name
        $this->assertEquals('cpu_usage', $dataPoint->getMeasurement());

        // Verify the fields
        $this->assertEquals(['value' => 85.5], $dataPoint->getFields());

        // Verify tags are empty by default
        $this->assertEquals([], $dataPoint->getTags());

        // Test adding a tag
        $dataPoint->addTag('host', 'server1');
        $this->assertEquals(['host' => 'server1'], $dataPoint->getTags());

        // Test method chaining
        $result = $dataPoint->addTag('region', 'us-west');
        $this->assertSame($dataPoint, $result, 'Method should return $this for chaining');
        $this->assertEquals(['host' => 'server1', 'region' => 'us-west'], $dataPoint->getTags());
    }
}
