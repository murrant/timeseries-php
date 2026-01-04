<?php

declare(strict_types=1);

namespace TimeseriesPhp\Bridge\Laravel;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Output\ConsoleOutput;
use TimeseriesPhp\Bridge\Laravel\Commands\TsdbRrdLabelsCommand;
use TimeseriesPhp\Core\Contracts\DriverCapabilities;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\RuntimeRegistry;
use TimeseriesPhp\Core\Contracts\TsdbConnection;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Discovery\DriverDiscovery;
use TimeseriesPhp\Core\Metrics\Repository\RuntimeMetricRepository;
use TimeseriesPhp\Core\Metrics\Repository\YamlMetricRepository;
use TimeseriesPhp\Core\TimeseriesManager;
use TimeseriesPhp\Driver\RRD\Contracts\LabelStrategy;
use TimeseriesPhp\Driver\RRD\FilenameLabelStrategy;

class TsdbServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        $this->app->singleton(TsdbManifestCompiler::class, fn ($app) => new TsdbManifestCompiler(
            $app['files'],
            $app->bootstrapPath('cache/timeseries_drivers.php')
        ));

        $this->app->singleton(TimeseriesManager::class);

        $this->app->resolving(TimeseriesManager::class, function (TimeseriesManager $manager) {
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

        $this->app->singleton(MetricRepository::class, function ($app) {
            $metrics = config('timeseries.metrics', ['repository' => 'runtime', 'path' => 'database/metrics.yaml']);

            return match ($metrics['repository']) {
                'yaml' => new YamlMetricRepository($app->basePath($metrics['path'])),
                default => new RuntimeMetricRepository,
            };
        });

        $this->app->singleton(TsdbManager::class, fn ($app) => new TsdbManager($app));

        $this->app->bind(TsdbConnection::class, fn ($app) => $app->make(TsdbManager::class)->driver());

        $this->app->bind(Writer::class, function ($app, ?string $connection = null) {
            $manager = $app->make(TsdbManager::class);
            $connection ??= $manager->getDefaultDriver();
            $config = $manager->getConfiguration($connection);
            $metadata = $manager->resolveMetadata($config['driver']);

            $loadedConfig = $manager->loadConfig($metadata->config, $config);

            return $app->make($metadata->writer, ['config' => $loadedConfig]);
        });

        $this->app->bind(QueryCompiler::class, function ($app, ?string $connection = null) {
            $manager = $app->make(TsdbManager::class);
            $connection ??= $manager->getDefaultDriver();
            $config = $manager->getConfiguration($connection);
            $metadata = $manager->resolveMetadata($config['driver']);

            $loadedConfig = $manager->loadConfig($metadata->config, $config);

            return $app->make($metadata->compiler, ['config' => $loadedConfig]);
        });

        $this->app->bind(DriverCapabilities::class, function ($app, ?string $connection = null) {
            $manager = $app->make(TsdbManager::class);
            $connection ??= $manager->getDefaultDriver();
            $config = $manager->getConfiguration($connection);
            $metadata = $manager->resolveMetadata($config['driver']);

            return $app->make($metadata->capabilities);
        });

        // TODO probably don't want driver specific bindings :I
        $this->app->bind(LabelStrategy::class, function ($app, ?string $connection = null) {
            $manager = $app->make(TsdbManager::class);
            $connection ??= $manager->getDefaultDriver();
            $config = $manager->getConfiguration($connection);

            if ($config['driver'] === 'rrd') {
                $metadata = $manager->resolveMetadata('rrd');
                $loadedConfig = $manager->loadConfig($metadata->config, $config);

                return $app->make(FilenameLabelStrategy::class, ['config' => $loadedConfig]);
            }

            throw new \RuntimeException("LabelStrategy not supported for driver: {$config['driver']}");
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
            ]);
        }
    }
}
