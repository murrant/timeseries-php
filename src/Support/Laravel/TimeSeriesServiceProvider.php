<?php

namespace TimeSeriesPhp\Support\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TimeSeriesPhp\Core\Factory\TSDBFactory;
use TimeSeriesPhp\Drivers\Graphite\Config\GraphiteConfig;
use TimeSeriesPhp\Drivers\Graphite\Driver as GraphiteDriver;
use TimeSeriesPhp\Drivers\InfluxDB\Config\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\Driver as InfluxDBDriver;
use TimeSeriesPhp\Drivers\Prometheus\Config\PrometheusConfig;
use TimeSeriesPhp\Drivers\Prometheus\Driver as PrometheusDriver;
use TimeSeriesPhp\Drivers\RRDtool\Config\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\Driver as RRDtoolDriver;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

class TimeSeriesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/time-series.php', 'time-series'
        );

        // Register driver classes
        TSDBFactory::registerDriver('influxdb', InfluxDBDriver::class, InfluxDBConfig::class);
        $this->app->bind(InfluxDBDriver::class);
        $this->app->alias(InfluxDBDriver::class, 'time-series.influxdb');

        TSDBFactory::registerDriver('rrdtool', RRDtoolDriver::class, RRDtoolConfig::class);
        $this->app->bind(RRDtoolDriver::class);
        $this->app->alias(RRDtoolDriver::class, 'time-series.rrdtool');

        TSDBFactory::registerDriver('prometheus', PrometheusDriver::class, PrometheusConfig::class);
        $this->app->bind(PrometheusDriver::class);
        $this->app->alias(PrometheusDriver::class, 'time-series.prometheus');

        TSDBFactory::registerDriver('graphite', GraphiteDriver::class, GraphiteConfig::class);
        $this->app->bind(GraphiteDriver::class);
        $this->app->alias(GraphiteDriver::class, 'time-series.graphite');

        // Register the time-series singleton
        $this->app->singleton('time-series', function (Application $app) {
            /** @var Repository $configRepository */
            $configRepository = $app->make('config');
            $driver = $configRepository->get('time-series.driver');

            if (! is_string($driver)) {
                throw new DriverException('Driver must be a string');
            }

            $driverConfig = $configRepository->get('time-series.drivers.'.$driver, []);

            if (! is_array($driverConfig)) {
                throw new DriverException('Driver configuration must be an array');
            }

            /** @var array<string, mixed> $driverConfig */

            // Create the driver config using TSDBFactory
            $config = TSDBFactory::createConfig($driver, $driverConfig);

            // Create and return the driver instance
            return TSDBFactory::create($driver, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Publish the configuration file
        if (function_exists('config_path')) {
            $this->publishes([
                __DIR__.'/../../config/time-series.php' => config_path('time-series.php'),
            ], 'time-series-config');
        } else {
            // Fallback for when not in a Laravel environment
            $this->publishes([
                __DIR__.'/../../config/time-series.php' => $this->app->basePath('config/time-series.php'),
            ], 'time-series-config');
        }
    }
}
