<?php

namespace TimeSeriesPhp\Tests\Core;

use DateTime;
use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\TSDB;

/**
 * Test-specific subclass of TimeSeries that allows injecting a mock driver
 */
class TestableTSDB extends TSDB
{
    public function __construct(TimeSeriesInterface $driver)
    {
        $this->driver = $driver;
    }
}

class TimeSeriesTest extends TestCase
{
    /**
     * @var TimeSeriesInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private TimeSeriesInterface $mockDriver;

    private TestableTSDB $timeSeries;

    protected function setUp(): void
    {
        $this->mockDriver = $this->createMock(TimeSeriesInterface::class);
        $this->timeSeries = new TestableTSDB($this->mockDriver);
    }

    public function test_write(): void
    {
        $measurement = 'cpu_usage';
        $fields = ['value' => 85.5];
        $tags = ['host' => 'server1'];
        $timestamp = new DateTime;

        $this->mockDriver->expects($this->once())
            ->method('write')
            ->with($this->callback(function (DataPoint $dataPoint) use ($measurement, $fields, $tags, $timestamp) {
                return $dataPoint->getMeasurement() === $measurement
                    && $dataPoint->getFields() === $fields
                    && $dataPoint->getTags() === $tags
                    && $dataPoint->getTimestamp() === $timestamp;
            }))
            ->willReturn(true);

        $result = $this->timeSeries->write($measurement, $fields, $tags, $timestamp);
        $this->assertTrue($result);
    }

    public function test_write_point(): void
    {
        $measurement = 'cpu_usage';
        $fields = ['value' => 85.5];
        $tags = ['host' => 'server1'];
        $timestamp = new DateTime;

        $this->mockDriver->expects($this->once())
            ->method('write')
            ->with($this->callback(function (DataPoint $dataPoint) use ($measurement, $fields, $tags, $timestamp) {
                return $dataPoint->getMeasurement() === $measurement
                    && $dataPoint->getFields() === $fields
                    && $dataPoint->getTags() === $tags
                    && $dataPoint->getTimestamp() === $timestamp;
            }))
            ->willReturn(true);

        $result = $this->timeSeries->writePoint($measurement, $fields, $tags, $timestamp);
        $this->assertTrue($result);
    }

    public function test_write_batch(): void
    {
        $dataPoints = [
            new DataPoint('cpu_usage', ['value' => 85.5], ['host' => 'server1']),
            new DataPoint('memory_usage', ['value' => 45.2], ['host' => 'server1']),
        ];

        $this->mockDriver->expects($this->once())
            ->method('writeBatch')
            ->with($dataPoints)
            ->willReturn(true);

        $result = $this->timeSeries->writeBatch($dataPoints);
        $this->assertTrue($result);
    }

    public function test_query(): void
    {
        $query = new Query('cpu_usage');
        /** @var QueryResult&\PHPUnit\Framework\MockObject\MockObject */
        $queryResult = $this->createMock(QueryResult::class);

        $this->mockDriver->expects($this->once())
            ->method('query')
            ->with($query)
            ->willReturn($queryResult);

        $result = $this->timeSeries->query($query);
        $this->assertSame($queryResult, $result);
    }

    public function test_query_last(): void
    {
        $measurement = 'cpu_usage';
        $field = 'value';
        $tags = ['host' => 'server1'];
        /** @var QueryResult&\PHPUnit\Framework\MockObject\MockObject */
        $queryResult = $this->createMock(QueryResult::class);

        $this->mockDriver->expects($this->once())
            ->method('query')
            ->with($this->callback(function (Query $query) use ($measurement, $field, $tags) {
                return $query->getMeasurement() === $measurement
                    && $query->getFields() === [$field]
                    && count($query->getConditions()) === count($tags)
                    && $query->getLimit() === 1
                    && $query->getOrderBy()['time'] === 'DESC';
            }))
            ->willReturn($queryResult);

        $result = $this->timeSeries->queryLast($measurement, $field, $tags);
        $this->assertSame($queryResult, $result);
    }

    public function test_query_first(): void
    {
        $measurement = 'cpu_usage';
        $field = 'value';
        $tags = ['host' => 'server1'];
        $queryResult = $this->createMock(QueryResult::class);

        $this->mockDriver->expects($this->once())
            ->method('query')
            ->with($this->callback(function (Query $query) use ($measurement, $field, $tags) {
                return $query->getMeasurement() === $measurement
                    && $query->getFields() === [$field]
                    && count($query->getConditions()) === count($tags)
                    && $query->getLimit() === 1
                    && $query->getOrderBy()['time'] === 'ASC';
            }))
            ->willReturn($queryResult);

        $result = $this->timeSeries->queryFirst($measurement, $field, $tags);
        $this->assertSame($queryResult, $result);
    }

    public function test_query_avg(): void
    {
        $measurement = 'cpu_usage';
        $field = 'value';
        $start = new DateTime('-1 hour');
        $end = new DateTime;
        $tags = ['host' => 'server1'];
        $queryResult = $this->createMock(QueryResult::class);

        $this->mockDriver->expects($this->once())
            ->method('query')
            ->with($this->callback(function (Query $query) use ($measurement, $field, $start, $end, $tags) {
                return $query->getMeasurement() === $measurement
                    && $query->getFields() === [$field]
                    && $query->getStartTime() === $start
                    && $query->getEndTime() === $end
                    && count($query->getConditions()) === count($tags)
                    && count($query->getAggregations()) === 1
                    && $query->getAggregations()[0]['function'] === 'AVG'
                    && $query->getAggregations()[0]['field'] === $field;
            }))
            ->willReturn($queryResult);

        $result = $this->timeSeries->queryAvg($measurement, $field, $start, $end, $tags);
        $this->assertSame($queryResult, $result);
    }

    public function test_delete_measurement(): void
    {
        $measurement = 'cpu_usage';
        $start = new DateTime('-1 hour');
        $end = new DateTime;

        $this->mockDriver->expects($this->once())
            ->method('deleteMeasurement')
            ->with($measurement, $start, $end)
            ->willReturn(true);

        $result = $this->timeSeries->deleteMeasurement($measurement, $start, $end);
        $this->assertTrue($result);
    }

    public function test_close(): void
    {
        $this->mockDriver->expects($this->once())
            ->method('close');

        $this->timeSeries->close();
    }
}
