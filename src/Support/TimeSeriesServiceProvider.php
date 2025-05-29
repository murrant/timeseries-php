<?php

namespace TimeSeriesPhp\Support;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TimeSeriesPhp\Config\DriverConfigFactory;
use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Drivers\Graphite\GraphiteConfig;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusConfig;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Exceptions\DriverException;

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
        TSDBFactory::registerDriver('influxdb', InfluxDBDriver::class);
        $this->app->bind(InfluxDBDriver::class);
        $this->app->alias(InfluxDBDriver::class, 'time-series.influxdb');

        TSDBFactory::registerDriver('rrdtool', RRDtoolDriver::class);
        $this->app->bind(RRDtoolDriver::class);
        $this->app->alias(RRDtoolDriver::class, 'time-series.rrdtool');

        TSDBFactory::registerDriver('prometheus', PrometheusDriver::class);
        $this->app->bind(PrometheusDriver::class);
        $this->app->alias(PrometheusDriver::class, 'time-series.prometheus');

        TSDBFactory::registerDriver('graphite', GraphiteDriver::class);
        $this->app->bind(GraphiteDriver::class);
        $this->app->alias(GraphiteDriver::class, 'time-series.graphite');

        // Register driver config classes
        DriverConfigFactory::registerDriverConfig('influxdb', InfluxDBConfig::class);
        DriverConfigFactory::registerDriverConfig('rrdtool', RRDtoolConfig::class);
        DriverConfigFactory::registerDriverConfig('prometheus', PrometheusConfig::class);
        DriverConfigFactory::registerDriverConfig('graphite', GraphiteConfig::class);

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

            // Create the driver config using the DriverConfigFactory
            $config = DriverConfigFactory::create($driver, $driverConfig);

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
