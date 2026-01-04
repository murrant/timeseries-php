<?php

declare(strict_types=1);

namespace Tests\InfluxDB2;

use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\MathOperator;
use TimeseriesPhp\Core\Enum\OperationType;
use TimeseriesPhp\Core\Enum\Operator;
use TimeseriesPhp\Core\Query\AST\DataQuery;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Core\Query\AST\LabelQuery;
use TimeseriesPhp\Core\Query\AST\Operations\BasicOperation;
use TimeseriesPhp\Core\Query\AST\Operations\MathOperation;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\Stream;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Driver\InfluxDB2\InfluxCompiler;
use TimeseriesPhp\Driver\InfluxDB2\InfluxConfig;

beforeEach(function (): void {
    $this->config = new InfluxConfig('localhost', 8086, 'token', 'org', 'bucket');
    $this->compiler = new InfluxCompiler($this->config);
});

it('compiles a simple data query', function (): void {
    $period = new TimeRange(
        new \DateTimeImmutable('2023-01-01 00:00:00', new \DateTimeZone('UTC')),
        new \DateTimeImmutable('2023-01-01 01:00:00', new \DateTimeZone('UTC'))
    );
    $query = new DataQuery(
        $period,
        Resolution::minutes(1),
        [
            new Stream('cpu_usage', [], [], [Aggregation::Average], 'cpu'),
        ]
    );

    $compiled = $this->compiler->compile($query);
    $flux = (string) $compiled;

    expect($flux)->toContain('rangeStart = time(v: "2023-01-01T00:00:00+00:00")');
    expect($flux)->toContain('rangeStop = time(v: "2023-01-01T01:00:00+00:00")');
    expect($flux)->toContain('from(bucket: "bucket")');
    expect($flux)->toContain('|> range(start: rangeStart, stop: rangeStop)');
    expect($flux)->toContain('|> filter(fn: (r) => r._measurement == "cpu_usage")');
    expect($flux)->toContain('|> aggregateWindow(every: 60s, fn: mean, createEmpty: false)');
    expect($flux)->toContain('|> yield(name: "cpu")');
});

it('compiles a data query with pipeline operations', function (): void {
    $period = new TimeRange(
        new \DateTimeImmutable('2023-01-01 00:00:00', new \DateTimeZone('UTC')),
        new \DateTimeImmutable('2023-01-01 01:00:00', new \DateTimeZone('UTC'))
    );
    $query = new DataQuery(
        $period,
        Resolution::auto(),
        [
            new Stream('cpu_usage', [], [
                new BasicOperation(OperationType::Rate),
                new MathOperation(MathOperator::Multiply, 100),
            ], []),
        ]
    );

    $compiled = $this->compiler->compile($query);
    $flux = (string) $compiled;

    expect($flux)->toContain('|> derivative(unit: 1s, nonNegative: true)');
    expect($flux)->toContain('|> map(fn: (r) => ({ r with _value: r._value * 100.0 })');
});

it('compiles a data query with filters', function (): void {
    $period = new TimeRange(
        new \DateTimeImmutable('2023-01-01 00:00:00', new \DateTimeZone('UTC')),
        new \DateTimeImmutable('2023-01-01 01:00:00', new \DateTimeZone('UTC'))
    );
    $query = new DataQuery(
        $period,
        Resolution::auto(),
        [
            new Stream('cpu_usage', [
                new Filter('host', Operator::Equal, 'server01'),
                new Filter('region', Operator::Regex, '^us-'),
            ], [], []),
        ]
    );

    $compiled = $this->compiler->compile($query);
    $flux = (string) $compiled;

    expect($flux)->toContain('|> filter(fn: (r) => r["host"] == "server01")');
    expect($flux)->toContain('|> filter(fn: (r) => r["region"] =~ "^us-")');
});

it('compiles a data query with IN filter', function (): void {
    $period = new TimeRange(
        new \DateTimeImmutable('2023-01-01 00:00:00', new \DateTimeZone('UTC')),
        new \DateTimeImmutable('2023-01-01 01:00:00', new \DateTimeZone('UTC'))
    );
    $query = new DataQuery(
        $period,
        Resolution::auto(),
        [
            new Stream('cpu_usage', [
                new Filter('host', Operator::In, ['server01', 'server02']),
            ], [], []),
        ]
    );

    $compiled = $this->compiler->compile($query);
    $flux = (string) $compiled;

    expect($flux)->toContain('|> filter(fn: (r) => contains(value: r["host"], set: ["server01", "server02"]))');
});

it('compiles a simple label query', function (): void {
    $query = new LabelQuery(null, [], [], null);

    $compiled = $this->compiler->compile($query);
    $flux = (string) $compiled;

    expect($flux)->toContain('import "influxdata/influxdb/schema"');
    expect($flux)->toContain('schema.measurements(bucket: "bucket")');
});

it('compiles a label values query', function (): void {
    $query = new LabelQuery('host', [], [], null);

    $compiled = $this->compiler->compile($query);
    $flux = (string) $compiled;

    expect($flux)->toContain('import "influxdata/influxdb/schema"');
    expect($flux)->toContain('schema.tagValues(bucket: "bucket", tag: "host")');
});

it('compiles a complex label query', function (): void {
    $period = new TimeRange(
        new \DateTimeImmutable('2023-01-01 00:00:00', new \DateTimeZone('UTC')),
        new \DateTimeImmutable('2023-01-01 01:00:00', new \DateTimeZone('UTC'))
    );
    $query = new LabelQuery('host', ['cpu_usage'], [
        new Filter('region', Operator::Equal, 'us-west'),
    ], $period);

    $compiled = $this->compiler->compile($query);
    $flux = (string) $compiled;

    expect($flux)->toContain('from(bucket: "bucket")');
    expect($flux)->toContain('|> range(start: time(v: "2023-01-01T00:00:00+00:00"), stop: time(v: "2023-01-01T01:00:00+00:00"))');
    expect($flux)->toContain('|> filter(fn: (r) => r._measurement == "cpu_usage")');
    expect($flux)->toContain('|> filter(fn: (r) => r["region"] == "us-west")');
    expect($flux)->toContain('|> keep(columns: ["host"])');
    expect($flux)->toContain('|> group()');
    expect($flux)->toContain('|> distinct(column: "host")');
});
