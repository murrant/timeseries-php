<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Laravel\Facades;

use DateTime;
use Illuminate\Support\Facades\Facade;
use Mockery;
use TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface;
use TimeSeriesPhp\Contracts\Schema\SchemaManagerInterface;
use TimeSeriesPhp\Core\Data\DataPoint;
use TimeSeriesPhp\Core\Data\QueryResult;
use TimeSeriesPhp\Core\Query\Query;
use TimeSeriesPhp\Laravel\Facades\TSDB as TSDBFacade;
use TimeSeriesPhp\Tests\Laravel\LaravelTestCase;
use TimeSeriesPhp\TSDB;

class TSDBFacadeTest extends LaravelTestCase
{
    private TSDB $tsdbMock;

    private TimeSeriesInterface $driverMock;

    private SchemaManagerInterface $schemaManagerMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->driverMock = Mockery::mock(TimeSeriesInterface::class);
        $this->schemaManagerMock = Mockery::mock(SchemaManagerInterface::class);
        $this->tsdbMock = Mockery::mock(TSDB::class);

        // Replace the real TSDB instance with our mock
        $this->container->instance(TSDB::class, $this->tsdbMock);

        // Set up the facade
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($this->container);
    }

    public function test_facade_write(): void
    {
        $measurement = 'cpu_usage';
        $fields = ['value' => 45.2];
        $tags = ['host' => 'server1'];
        $timestamp = new DateTime;

        $this->tsdbMock->shouldReceive('write')
            ->once()
            ->with($measurement, $fields, $tags, $timestamp)
            ->andReturn(true);

        $result = TSDBFacade::write($measurement, $fields, $tags, $timestamp);
        $this->assertTrue($result);
    }

    public function test_facade_write_batch(): void
    {
        $dataPoints = [
            new DataPoint('cpu_usage', ['value' => 45.2], ['host' => 'server1']),
            new DataPoint('memory_usage', ['value' => 1024], ['host' => 'server1']),
        ];

        $this->tsdbMock->shouldReceive('writeBatch')
            ->once()
            ->with($dataPoints)
            ->andReturn(true);

        $result = TSDBFacade::writeBatch($dataPoints);
        $this->assertTrue($result);
    }

    public function test_facade_query(): void
    {
        $query = new Query('cpu_usage');
        $queryResult = new QueryResult;

        $this->tsdbMock->shouldReceive('query')
            ->once()
            ->with($query)
            ->andReturn($queryResult);

        $result = TSDBFacade::query($query);
        $this->assertSame($queryResult, $result);
    }

    public function test_facade_query_last(): void
    {
        $measurement = 'cpu_usage';
        $field = 'value';
        $tags = ['host' => 'server1'];
        $queryResult = new QueryResult;

        $this->tsdbMock->shouldReceive('queryLast')
            ->once()
            ->with($measurement, $field, $tags)
            ->andReturn($queryResult);

        $result = TSDBFacade::queryLast($measurement, $field, $tags);
        $this->assertSame($queryResult, $result);
    }
}
