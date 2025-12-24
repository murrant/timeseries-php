<?php

declare(strict_types=1);

namespace TimeseriesPhp\Bridge\Laravel;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use TimeseriesPhp\Core\Contracts\GraphCompiler;
use TimeseriesPhp\Core\Contracts\GraphRepository;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Contracts\TsdbCapabilities;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Contracts\TsdbIngestor;
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

        $driver = config('tsdb.driver', 'Null');
        $this->app->bind(TsdbIngestor::class, "\\TimeseriesPhp\\Driver\\{$driver}\\{$driver}Ingestor");
        $this->app->bind(GraphCompiler::class, "\\TimeseriesPhp\\Driver\\{$driver}\\{$driver}Compiler");
        $this->app->bind(TsdbClient::class, "\\TimeseriesPhp\\Driver\\{$driver}\\{$driver}Client");
        $this->app->bind(TsdbCapabilities::class, "\\TimeseriesPhp\\Driver\\{$driver}\\{$driver}Capabilities");
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/tsdb.php' => config_path('tsdb.php'),
        ]);
    }
}
