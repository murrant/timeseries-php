<?php

declare(strict_types=1);

namespace Tests\InfluxDB2;

use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Query\AST\LabelQuery;
use TimeseriesPhp\Core\Results\LabelQueryResult;
use TimeseriesPhp\Driver\InfluxDB2\InfluxConfig;
use TimeseriesPhp\Driver\InfluxDB2\InfluxLabelDiscovery;

beforeEach(function (): void {
    $this->config = new InfluxConfig('localhost', 8086, 'token', 'org', 'bucket');
    $this->compiler = mock(QueryCompiler::class);
    $this->executor = mock(QueryExecutor::class);
    $this->discovery = new InfluxLabelDiscovery($this->config, $this->compiler, $this->executor);
});

it('lists labels for metrics', function (): void {
    $metrics = ['cpu', 'mem'];
    $filters = [];

    $this->compiler->shouldReceive('compile')
        ->withArgs(fn (LabelQuery $query) => $query->label === null && $query->metrics === $metrics)
        ->andReturn(mock(\TimeseriesPhp\Core\Contracts\CompiledQuery::class));

    $this->executor->shouldReceive('execute')
        ->andReturn(new LabelQueryResult(['host', 'region'], []));

    $labels = $this->discovery->listLabels($metrics, $filters);

    expect($labels)->toBe(['host', 'region']);
});

it('lists label values for metrics', function (): void {
    $label = 'host';
    $metrics = ['cpu'];
    $filters = [];

    $this->compiler->shouldReceive('compile')
        ->withArgs(fn (LabelQuery $query) => $query->label === $label && $query->metrics === $metrics)
        ->andReturn(mock(\TimeseriesPhp\Core\Contracts\CompiledQuery::class));

    $this->executor->shouldReceive('execute')
        ->andReturn(new LabelQueryResult([], ['server1', 'server2']));

    $values = $this->discovery->listLabelValues($label, $metrics, $filters);

    expect($values)->toBe(['server1', 'server2']);
});
