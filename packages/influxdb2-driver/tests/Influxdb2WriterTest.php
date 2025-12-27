<?php

declare(strict_types=1);

namespace Tests\InfluxDB2;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;
use TimeseriesPhp\Core\Metrics\MetricSample;
use TimeseriesPhp\Driver\InfluxDB2\InfluxConfig;
use TimeseriesPhp\Driver\InfluxDB2\InfluxWriter;

it('writes a metric sample to influxdb2', function (): void {
    $httpClient = mock(ClientInterface::class);
    $requestFactory = mock(RequestFactoryInterface::class);
    $streamFactory = mock(StreamFactoryInterface::class);
    $request = mock(RequestInterface::class);
    $response = mock(ResponseInterface::class);
    $stream = mock(StreamInterface::class);

    $url = 'http://localhost:8086';
    $token = 'test-token';
    $org = 'test-org';
    $bucket = 'test-bucket';

    $sample = new MetricSample(
        new MetricIdentifier('app', 'cpu_usage'),
        ['host' => 'server01', 'region' => 'us-west'],
        42.5,
        new \DateTimeImmutable('2023-10-27 12:00:00', new \DateTimeZone('UTC'))
    );

    $expectedLine = 'app_cpu_usage,host=server01,region=us-west value=42.500000 1698408000';
    $expectedUrl = 'http://localhost:8086/api/v2/write?org=test-org&bucket=test-bucket&precision=s';

    $requestFactory->shouldReceive('createRequest')
        ->once()
        ->with('POST', $expectedUrl)
        ->andReturn($request);

    $request->shouldReceive('withHeader')
        ->with('Authorization', 'Token test-token')
        ->andReturnSelf();

    $request->shouldReceive('withHeader')
        ->with('Content-Type', 'text/plain; charset=utf-8')
        ->andReturnSelf();

    $streamFactory->shouldReceive('createStream')
        ->once()
        ->with($expectedLine)
        ->andReturn($stream);

    $request->shouldReceive('withBody')
        ->once()
        ->with($stream)
        ->andReturnSelf();

    $httpClient->shouldReceive('sendRequest')
        ->once()
        ->with($request)
        ->andReturn($response);

    $response->shouldReceive('getStatusCode')
        ->andReturn(204);

    $writer = new InfluxWriter(
        new InfluxConfig($url, $token, $org, $bucket),
        $httpClient,
        $requestFactory,
        $streamFactory,
    );

    $writer->write($sample);
});

it('escapes special characters in line protocol', function (): void {
    $httpClient = mock(ClientInterface::class);
    $requestFactory = mock(RequestFactoryInterface::class);
    $streamFactory = mock(StreamFactoryInterface::class);
    $request = mock(RequestInterface::class);
    $response = mock(ResponseInterface::class);
    $stream = mock(StreamInterface::class);

    $sample = new MetricSample(
        new MetricIdentifier('my namespace', 'my,metric'),
        ['label=name' => 'label value'],
        100,
        new \DateTimeImmutable('2023-10-27 12:00:00', new \DateTimeZone('UTC'))
    );

    // namespace and name are joined by _
    // 'my namespace' -> 'my\ namespace'
    // 'my,metric' -> 'my\,metric'
    // Result: 'my\ namespace_my\,metric'
    $expectedLine = 'my\ namespace_my\,metric,label\=name=label\ value value=100i 1698408000';

    $requestFactory->shouldReceive('createRequest')->andReturn($request);
    $request->shouldReceive('withHeader')->andReturnSelf();
    $request->shouldReceive('withBody')->andReturnSelf();
    $streamFactory->shouldReceive('createStream')->with($expectedLine)->andReturn($stream);
    $httpClient->shouldReceive('sendRequest')->andReturn($response);
    $response->shouldReceive('getStatusCode')->andReturn(204);

    $writer = new InfluxWriter(new InfluxConfig('url', 'token', 'org', 'bucket'), $httpClient, $requestFactory, $streamFactory);
    $writer->write($sample);

    expect(true)->toBeTrue();
});
