<?php

namespace TimeSeriesPhp\Tests\Core;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Core\Data\DataPoint;

class DataPointTest extends TestCase
{
    public function test_constructor(): void
    {
        $measurement = 'cpu_usage';
        $fields = ['usage_user' => 23.5, 'usage_system' => 12.1];
        $tags = ['host' => 'server01', 'region' => 'us-west'];
        $timestamp = new DateTime('2023-01-01 12:00:00');

        $dataPoint = new DataPoint($measurement, $fields, $tags, $timestamp);

        $this->assertEquals($measurement, $dataPoint->getMeasurement());
        $this->assertEquals($fields, $dataPoint->getFields());
        $this->assertEquals($tags, $dataPoint->getTags());
        $this->assertSame($timestamp, $dataPoint->getTimestamp());
    }

    public function test_constructor_with_default_timestamp(): void
    {
        $before = new DateTime;
        $dataPoint = new DataPoint('cpu_usage', ['value' => 10]);
        $after = new DateTime;

        $this->assertInstanceOf(DateTime::class, $dataPoint->getTimestamp());

        // Timestamp should be between before and after
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $dataPoint->getTimestamp()->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $dataPoint->getTimestamp()->getTimestamp());
    }

    public function test_constructor_with_default_tags(): void
    {
        $dataPoint = new DataPoint('cpu_usage', ['value' => 10]);
        $this->assertEquals([], $dataPoint->getTags());
    }

    public function test_add_tag(): void
    {
        $dataPoint = new DataPoint('cpu_usage', ['value' => 10]);
        $result = $dataPoint->addTag('host', 'server01');

        $this->assertSame($dataPoint, $result, 'Method should return $this for chaining');
        $this->assertEquals(['host' => 'server01'], $dataPoint->getTags());

        // Test adding multiple tags
        $dataPoint->addTag('region', 'us-west');
        $this->assertEquals(['host' => 'server01', 'region' => 'us-west'], $dataPoint->getTags());

        // Test overwriting existing tag
        $dataPoint->addTag('host', 'server02');
        $this->assertEquals(['host' => 'server02', 'region' => 'us-west'], $dataPoint->getTags());
    }

    public function test_add_field(): void
    {
        $dataPoint = new DataPoint('cpu_usage', ['usage_user' => 23.5]);
        $result = $dataPoint->addField('usage_system', 12.1);

        $this->assertSame($dataPoint, $result, 'Method should return $this for chaining');
        $this->assertEquals(['usage_user' => 23.5, 'usage_system' => 12.1], $dataPoint->getFields());

        // Test overwriting existing field
        $dataPoint->addField('usage_user', 25.0);
        $this->assertEquals(['usage_user' => 25.0, 'usage_system' => 12.1], $dataPoint->getFields());
    }

    public function test_method_chaining(): void
    {
        $dataPoint = new DataPoint('cpu_usage', ['usage_user' => 23.5]);
        $dataPoint->addTag('host', 'server01')
            ->addTag('region', 'us-west')
            ->addField('usage_system', 12.1)
            ->addField('usage_idle', 64.4);

        $this->assertEquals('cpu_usage', $dataPoint->getMeasurement());
        $this->assertEquals(
            ['usage_user' => 23.5, 'usage_system' => 12.1, 'usage_idle' => 64.4],
            $dataPoint->getFields()
        );
        $this->assertEquals(
            ['host' => 'server01', 'region' => 'us-west'],
            $dataPoint->getTags()
        );
    }
}
