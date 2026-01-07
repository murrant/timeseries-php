<?php

declare(strict_types=1);

namespace TimeseriesPhp\Bridge\Laravel;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Output\ConsoleOutput;
use TimeseriesPhp\Bridge\Laravel\Commands\TsdbRrdInfoCommand;
use TimeseriesPhp\Bridge\Laravel\Commands\TsdbRrdLabelsCommand;
use TimeseriesPhp\Core\Contracts\LabelDiscovery;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Discovery\DriverDiscovery;
use TimeseriesPhp\Core\Metrics\Repository\RuntimeMetricRepository;
use TimeseriesPhp\Core\Metrics\Repository\YamlMetricRepository;
use TimeseriesPhp\Core\TimeseriesManager;

class TsdbServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        // FIXME remove or update?
        $this->app->singleton(TsdbManifestCompiler::class, fn ($app) => new TsdbManifestCompiler(
            $app['files'],
            $app->bootstrapPath('cache/timeseries_drivers.php')
        ));

        $this->app->singleton(MetricRepository::class, function ($app) {
            $metrics = config('timeseries.metrics', ['repository' => 'runtime', 'path' => 'database/metrics.yaml']);

            return match ($metrics['repository']) {
                'yaml' => new YamlMetricRepository($app->basePath($metrics['path'])),
                default => new RuntimeMetricRepository,
            };
        });

        $this->app->singleton(TimeseriesManager::class);

        $this->app->resolving(TimeseriesManager::class, function (TimeseriesManager $manager): void {
            // discover drivers and create factories
            foreach (DriverDiscovery::discover() as $name => $factory) {
                $manager->registerDriver($name, $this->app->make($factory));
            }

            // set up connection runtimes
            $manager->setDefaultConnection(config('timeseries.default'));
            foreach (config('timeseries.connections') as $name => $cfg) {
                $manager->addConnection($name, $cfg['driver'], $cfg);
            }
        });

        // convenience bindings, mainly the user can use TimeseriesManager directly.
        $this->app->bind(Writer::class, function ($app, array $arguments = []) {
            $connection = $arguments['connection'] ?? null;
            /** @var TimeseriesManager $manager */
            $manager = $app->make(TimeseriesManager::class);

            return $manager->connection($connection)->writer();
        });

        $this->app->bind(QueryCompiler::class, function ($app, array $arguments = []) {
            $connection = $arguments['connection'] ?? null;
            /** @var TimeseriesManager $manager */
            $manager = $app->make(TimeseriesManager::class);

            return $manager->connection($connection)->compiler();
        });

        $this->app->bind(QueryExecutor::class, function ($app, array $arguments = []) {
            $connection = $arguments['connection'] ?? null;
            /** @var TimeseriesManager $manager */
            $manager = $app->make(TimeseriesManager::class);

            return $manager->connection($connection)->compiler();
        });

        $this->app->bind(LabelDiscovery::class, function ($app, array $arguments = []) {
            $connection = $arguments['connection'] ?? null;
            /** @var TimeseriesManager $manager */
            $manager = $app->make(TimeseriesManager::class);

            return $manager->connection($connection)->labels();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/timeseries.php' => config_path('timeseries.php'),
        ]);

        if ($this->app->runningInConsole()) {
            // TODO review this approach to caching
            Event::listen(CommandFinished::class, function (CommandFinished $event): void {
                if ($event->command === 'optimize') {
                    $this->app->make(TsdbManifestCompiler::class)->compile();
                    (new ConsoleOutput)->writeln('<info>TSDB driver manifest cached!</info>');
                }

                if ($event->command === 'optimize:clear') {
                    $this->app->make(TsdbManifestCompiler::class)->clear();
                    (new ConsoleOutput)->writeln('<info>TSDB driver manifest cleared!</info>');
                }
            });

            $this->commands([
                TsdbRrdLabelsCommand::class,
                TsdbRrdInfoCommand::class,
            ]);
        }
    }
}
