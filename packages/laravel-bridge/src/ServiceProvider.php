<?php

declare(strict_types=1);

namespace TimeseriesPhp\Bridge\Laravel;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use TimeseriesPhp\Core\Contracts\GraphRepository;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\TsdbCapabilities;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Contracts\TsdbWriter;
use TimeseriesPhp\Core\DriverResolver;
use TimeseriesPhp\Core\Graph\Repository\RuntimeGraphRepository;
use TimeseriesPhp\Core\Graph\Repository\YamlGraphRepository;
use TimeseriesPhp\Core\Metrics\Repository\RuntimeMetricRepository;
use TimeseriesPhp\Core\Metrics\Repository\YamlMetricRepository;

class ServiceProvider extends BaseServiceProvider
{
    #[\Override]
    public function register(): void
    {

        $this->app->singleton(GraphRepository::class, function ($app) {
            $graphs = config('tsdb.graphs', ['repository' => 'runtime', 'path' => 'database/graphs']);

            return match ($graphs['repository']) {
                'yaml' => new YamlGraphRepository($graphs['path'], $app->make(MetricRepository::class)),
                default => new RuntimeGraphRepository($app->make(MetricRepository::class)),
            };
        });
        $this->app->singleton(MetricRepository::class, function ($app) {
            $metrics = config('tsdb.metrics', ['repository' => 'runtime', 'path' => 'database/metrics.yaml']);

            return match ($metrics['repository']) {
                'yaml' => new YamlMetricRepository($metrics['path']),
                default => new RuntimeMetricRepository,
            };
        });

        $driver = config('tsdb.driver', 'null');
        $discoverDrivers = DriverResolver::discoverDrivers();
        // TODO throw non-existent driver exception

        foreach ($discoverDrivers as $driverClass) {
            $definition = DriverResolver::resolve($driverClass);
            $this->app->bind($definition->config, function () use ($definition) {
                $configClass = $definition->config;

                return $configClass::fromArray(config("tsdb.drivers.$definition->name", []));
            });

            if ($definition->name == $driver) {
                $this->app->bind(TsdbWriter::class, $definition->writer);
                $this->app->bind(QueryCompiler::class, $definition->compiler);
                $this->app->bind(TsdbClient::class, $definition->client);
                $this->app->bind(TsdbCapabilities::class, $definition->capabilities);
            }
        }
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/tsdb.php' => config_path('tsdb.php'),
        ]);
    }
}
