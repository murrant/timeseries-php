<?php

declare(strict_types=1);

namespace TimeseriesPhp\Bridge\Laravel;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Output\ConsoleOutput;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\TsdbCapabilities;
use TimeseriesPhp\Core\Contracts\TsdbConnection;
use TimeseriesPhp\Core\Contracts\TsdbWriter;
use TimeseriesPhp\Core\Metrics\Repository\RuntimeMetricRepository;
use TimeseriesPhp\Core\Metrics\Repository\YamlMetricRepository;
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

        $this->app->singleton(MetricRepository::class, function ($app) {
            $metrics = config('timeseries.metrics', ['repository' => 'runtime', 'path' => 'database/metrics.yaml']);

            return match ($metrics['repository']) {
                'yaml' => new YamlMetricRepository($app->basePath($metrics['path'])),
                default => new RuntimeMetricRepository,
            };
        });

        $this->app->singleton(TsdbManager::class, fn ($app) => new TsdbManager($app));

        $this->app->bind(TsdbConnection::class, fn ($app) => $app->make(TsdbManager::class)->driver());

        $this->app->bind(TsdbWriter::class, function ($app) {
            $manager = $app->make(TsdbManager::class);
            $connection = $manager->getDefaultDriver();
            $config = config('timeseries.connections.'.$connection);
            $metadata = $manager->resolveMetadata($config['driver']);
            $writer = $metadata->writer;

            $loadedConfig = $manager->loadConfig($metadata->config, $config);

            return $app->make($writer, ['config' => $loadedConfig]);
        });

        $this->app->bind(QueryCompiler::class, function ($app) {
            $manager = $app->make(TsdbManager::class);
            $connection = $manager->getDefaultDriver();
            $driver = config('timeseries.connections.'.$connection.'.driver');
            $compiler = $manager->resolveMetadata($driver)->compiler;

            return $app->make($compiler);
        });

        $this->app->bind(TsdbCapabilities::class, function ($app) {
            $manager = $app->make(TsdbManager::class);
            $connection = $manager->getDefaultDriver();
            $driver = config('timeseries.connections.'.$connection.'.driver');
            $capabilities = $manager->resolveMetadata($driver)->capabilities;

            return $app->make($capabilities);
        });

        // TODO probably don't want driver specific bindings :I
        $this->app->bind(LabelStrategy::class, FilenameLabelStrategy::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/timeseries.php' => config_path('timeseries.php'),
        ]);

        if ($this->app->runningInConsole()) {
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
        }
    }
}
