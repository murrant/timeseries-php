<?php

declare(strict_types=1);

namespace Tests\Driver\Aggregate;

use TimeseriesPhp\Core\Contracts\DriverFactory;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;
use TimeseriesPhp\Core\Metrics\MetricSample;
use TimeseriesPhp\Core\Runtime;
use TimeseriesPhp\Core\Services\DriverServiceRegistry;
use TimeseriesPhp\Core\TimeseriesManager;
use TimeseriesPhp\Driver\Aggregate\AggregateFactory;

it('aggregates writes to multiple connections', function (): void {
    $manager = new TimeseriesManager;

    // Mock writers
    /** @var \Mockery\MockInterface&Writer $writer1 */
    $writer1 = mock(Writer::class);
    /** @var \Mockery\MockInterface&Writer $writer2 */
    $writer2 = mock(Writer::class);

    // Register dummy drivers that return our mock writers
    $factory1 = mock(DriverFactory::class);
    $factory1->shouldReceive('make')->andReturn(new Runtime(
        mock(\TimeseriesPhp\Core\Contracts\DriverConfig::class),
        new DriverServiceRegistry([Writer::class => $writer1]),
        mock(\TimeseriesPhp\Core\Contracts\MetricRepository::class)
    ));

    $factory2 = mock(DriverFactory::class);
    $factory2->shouldReceive('make')->andReturn(new Runtime(
        mock(\TimeseriesPhp\Core\Contracts\DriverConfig::class),
        new DriverServiceRegistry([Writer::class => $writer2]),
        mock(\TimeseriesPhp\Core\Contracts\MetricRepository::class)
    ));

    $manager->registerDriver('d1', $factory1);
    $manager->registerDriver('d2', $factory2);

    $manager->addConnection('c1', 'd1', []);
    $manager->addConnection('c2', 'd2', []);

    // Create aggregate driver
    $aggregateFactory = new AggregateFactory($manager);
    $manager->registerDriver('aggregate', $aggregateFactory);
    $manager->addConnection('multi', 'aggregate', ['connections' => ['c1', 'c2']]);

    $metric = new MetricIdentifier('ns', 'name');
    $sample = new MetricSample($metric, [], 1.0);

    $writer1->shouldReceive('write')->once()->with($sample);
    $writer2->shouldReceive('write')->once()->with($sample);

    $manager->connection('multi')->writer()->write($sample);
});

it('aggregates writeBatch to multiple connections', function (): void {
    $manager = new TimeseriesManager;

    // Mock writers
    /** @var \Mockery\MockInterface&Writer $writer1 */
    $writer1 = mock(Writer::class);
    /** @var \Mockery\MockInterface&Writer $writer2 */
    $writer2 = mock(Writer::class);

    // Register dummy drivers
    $factory = mock(DriverFactory::class);
    $factory->shouldReceive('make')->andReturn(new Runtime(
        mock(\TimeseriesPhp\Core\Contracts\DriverConfig::class),
        new DriverServiceRegistry([Writer::class => $writer1]),
        mock(\TimeseriesPhp\Core\Contracts\MetricRepository::class)
    ), new Runtime(
        mock(\TimeseriesPhp\Core\Contracts\DriverConfig::class),
        new DriverServiceRegistry([Writer::class => $writer2]),
        mock(\TimeseriesPhp\Core\Contracts\MetricRepository::class)
    ));

    $manager->registerDriver('d', $factory);
    $manager->addConnection('c1', 'd', []);
    $manager->addConnection('c2', 'd', []);

    // Create aggregate driver
    $aggregateFactory = new AggregateFactory($manager);
    $manager->registerDriver('aggregate', $aggregateFactory);
    $manager->addConnection('multi', 'aggregate', ['connections' => ['c1', 'c2']]);

    $metric = new MetricIdentifier('ns', 'name');
    $samples = [
        new MetricSample($metric, [], 1.0),
        new MetricSample($metric, [], 2.0),
    ];

    $writer1->shouldReceive('writeBatch')->once()->with($samples);
    $writer2->shouldReceive('writeBatch')->once()->with($samples);

    $manager->connection('multi')->writer()->writeBatch($samples);
});
