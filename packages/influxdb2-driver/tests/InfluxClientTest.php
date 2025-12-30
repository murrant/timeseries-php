<?php

declare(strict_types=1);

namespace Tests\InfluxDB2;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use TimeseriesPhp\Core\Enum\QueryType;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\LabelResult;
use TimeseriesPhp\Core\Results\TimeSeriesResult;
use TimeseriesPhp\Driver\InfluxDB2\InfluxClient;
use TimeseriesPhp\Driver\InfluxDB2\InfluxConfig;
use TimeseriesPhp\Driver\InfluxDB2\InfluxQuery;

it('executes a data query and returns TimeSeriesResult', function (): void {
    $httpClient = mock(ClientInterface::class);
    $requestFactory = mock(RequestFactoryInterface::class);
    $streamFactory = mock(StreamFactoryInterface::class);
    $request = mock(RequestInterface::class);
    $response = mock(ResponseInterface::class);
    $stream = mock(StreamInterface::class);
    $responseStream = mock(StreamInterface::class);

    $config = new InfluxConfig('http://localhost', 8086, 'token', 'org', 'bucket');
    $range = new TimeRange(new \DateTimeImmutable('2023-01-01 00:00:00 UTC'), new \DateTimeImmutable('2023-01-01 01:00:00 UTC'));
    $resolution = Resolution::minutes(1);
    $query = new InfluxQuery(['from(bucket: "bucket")'], $range, $resolution);

    $csv = "#datatype,string,long,dateTime:RFC3339,dateTime:RFC3339,dateTime:RFC3339,double,string,string,string\n"
        .",result,table,_start,_stop,_time,_value,_field,_measurement,host\n"
        .",_result,0,2023-01-01T00:00:00Z,2023-01-01T01:00:00Z,2023-01-01T00:00:00Z,10,value,cpu,server1\n"
        .",_result,0,2023-01-01T00:00:00Z,2023-01-01T01:00:00Z,2023-01-01T00:01:00Z,12,value,cpu,server1\n";

    $requestFactory->shouldReceive('createRequest')->andReturn($request);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $streamFactory->shouldReceive('createStream')->andReturn($stream);
    $httpClient->shouldReceive('sendRequest')->with($request)->andReturn($response);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseStream);
    $responseStream->shouldReceive('getContents')->andReturn($csv);

    $client = new InfluxClient($config, $httpClient, $requestFactory, $streamFactory);
    $result = $client->execute($query);

    expect($result)->toBeInstanceOf(TimeSeriesResult::class);
    expect($result->series)->toHaveCount(1);
    expect($result->series[0]->metric)->toBe('cpu');
    expect($result->series[0]->labels)->toBe(['host' => 'server1']);
    expect($result->series[0]->points)->toHaveCount(2);
    expect($result->series[0]->points[0]->value)->toBe(10.0);
    expect($result->series[0]->points[1]->value)->toBe(12.0);
});

it('executes a label query and returns LabelResult', function (): void {
    $httpClient = mock(ClientInterface::class);
    $requestFactory = mock(RequestFactoryInterface::class);
    $streamFactory = mock(StreamFactoryInterface::class);
    $request = mock(RequestInterface::class);
    $response = mock(ResponseInterface::class);
    $stream = mock(StreamInterface::class);
    $responseStream = mock(StreamInterface::class);

    $config = new InfluxConfig('http://localhost', 8086, 'token', 'org', 'bucket');
    $range = new TimeRange(new \DateTimeImmutable('2023-01-01 00:00:00 UTC'), new \DateTimeImmutable('2023-01-01 01:00:00 UTC'));
    $query = new InfluxQuery(['schema.measurements(bucket: "bucket")'], $range, Resolution::auto(), QueryType::Label);

    // InfluxDB metadata query (schema.measurements) returns results in _value column
    $csv = "#datatype,string,long,string\n"
        .",result,table,_value\n"
        .",_result,0,cpu\n"
        .",_result,0,mem\n";

    $requestFactory->shouldReceive('createRequest')->andReturn($request);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $streamFactory->shouldReceive('createStream')->andReturn($stream);
    $httpClient->shouldReceive('sendRequest')->with($request)->andReturn($response);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseStream);
    $responseStream->shouldReceive('getContents')->andReturn($csv);

    $client = new InfluxClient($config, $httpClient, $requestFactory, $streamFactory);
    $result = $client->execute($query);

    expect($result)->toBeInstanceOf(LabelResult::class);
    expect($result->values)->toBe(['cpu', 'mem']);
});

it('executes a tag values query and returns LabelResult', function (): void {
    $httpClient = mock(ClientInterface::class);
    $requestFactory = mock(RequestFactoryInterface::class);
    $streamFactory = mock(StreamFactoryInterface::class);
    $request = mock(RequestInterface::class);
    $response = mock(ResponseInterface::class);
    $stream = mock(StreamInterface::class);
    $responseStream = mock(StreamInterface::class);

    $config = new InfluxConfig('http://localhost', 8086, 'token', 'org', 'bucket');
    $range = new TimeRange(new \DateTimeImmutable('2023-01-01 00:00:00 UTC'), new \DateTimeImmutable('2023-01-01 01:00:00 UTC'));
    $query = new InfluxQuery(['schema.tagValues(bucket: "bucket", tag: "host")'], $range, Resolution::auto(), QueryType::Label);

    $csv = "#datatype,string,long,string\n"
        .",result,table,_value\n"
        .",_result,0,server1\n"
        .",_result,0,server2\n";

    $requestFactory->shouldReceive('createRequest')->andReturn($request);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $streamFactory->shouldReceive('createStream')->andReturn($stream);
    $httpClient->shouldReceive('sendRequest')->with($request)->andReturn($response);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseStream);
    $responseStream->shouldReceive('getContents')->andReturn($csv);

    $client = new InfluxClient($config, $httpClient, $requestFactory, $streamFactory);
    $result = $client->execute($query);

    expect($result)->toBeInstanceOf(LabelResult::class);
    expect($result->values)->toBe(['server1', 'server2']);
});

it('handles multiple results in TimeSeriesResult', function (): void {
    $httpClient = mock(ClientInterface::class);
    $requestFactory = mock(RequestFactoryInterface::class);
    $streamFactory = mock(StreamFactoryInterface::class);
    $request = mock(RequestInterface::class);
    $response = mock(ResponseInterface::class);
    $stream = mock(StreamInterface::class);
    $responseStream = mock(StreamInterface::class);

    $config = new InfluxConfig('http://localhost', 8086, 'token', 'org', 'bucket');
    $range = new TimeRange(new \DateTimeImmutable('2023-01-01 00:00:00 UTC'), new \DateTimeImmutable('2023-01-01 01:00:00 UTC'));
    $resolution = Resolution::minutes(1);
    $query = new InfluxQuery(['from(bucket: "bucket")'], $range, $resolution);

    $csv = "#datatype,string,long,dateTime:RFC3339,dateTime:RFC3339,dateTime:RFC3339,double,string,string,string\n"
        .",result,table,_start,_stop,_time,_value,_field,_measurement,host\n"
        .",in,0,2023-01-01T00:00:00Z,2023-01-01T01:00:00Z,2023-01-01T00:00:00Z,10,value,traffic,router1\n"
        .",out,0,2023-01-01T00:00:00Z,2023-01-01T01:00:00Z,2023-01-01T00:00:00Z,20,value,traffic,router1\n";

    $requestFactory->shouldReceive('createRequest')->andReturn($request);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $streamFactory->shouldReceive('createStream')->andReturn($stream);
    $httpClient->shouldReceive('sendRequest')->with($request)->andReturn($response);
    $response->shouldReceive('getStatusCode')->andReturn(200);
    $response->shouldReceive('getBody')->andReturn($responseStream);
    $responseStream->shouldReceive('getContents')->andReturn($csv);

    $client = new InfluxClient($config, $httpClient, $requestFactory, $streamFactory);
    $result = $client->execute($query);

    expect($result)->toBeInstanceOf(TimeSeriesResult::class);
    expect($result->series)->toHaveCount(2);

    $inSeries = collect($result->series)->first(fn ($s) => $s->metric === 'in');
    $outSeries = collect($result->series)->first(fn ($s) => $s->metric === 'out');

    expect($inSeries)->not->toBeNull();
    expect($outSeries)->not->toBeNull();

    expect($inSeries->points[0]->value)->toBe(10.0);
    expect($outSeries->points[0]->value)->toBe(20.0);
});
