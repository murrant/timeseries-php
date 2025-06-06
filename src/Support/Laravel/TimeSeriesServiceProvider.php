<?php

namespace TimeSeriesPhp\Support\Laravel;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TimeSeriesPhp\Core\Factory\DriverManager;
use TimeSeriesPhp\Drivers\Graphite\Config\GraphiteConfig;
use TimeSeriesPhp\Drivers\Graphite\GraphiteDriver;
use TimeSeriesPhp\Drivers\InfluxDB\Config\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\Prometheus\Config\PrometheusConfig;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Drivers\RRDtool\Config\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Exceptions\Driver\DriverException;

/**
 * Laravel Service Provider for TimeSeriesPhp
 *
 * This service provider registers the TimeSeriesPhp library with Laravel.
 * It registers all available drivers, binds them to the Laravel container,
 * and sets up a singleton for the 'time-series' service.
 *
 * To use this service provider, add it to the providers array in config/app.php:
 *
 * ```php
 * 'providers' => [
 *     // Other Service Providers
 *     TimeSeriesPhp\Support\Laravel\TimeSeriesServiceProvider::class,
 * ],
 * ```
 *
 * Then publish the configuration file:
 *
 * ```bash
 * php artisan vendor:publish --tag=time-series-config
 * ```
 *
 * You can then use the 'time-series' service in your application:
 *
 * ```php
 * $db = app('time-series');
 * ```
 *
 * Or inject it into your controllers:
 *
 * ```php
 * public function index(\TimeSeriesPhp\Contracts\Driver\TimeSeriesInterface $timeSeries)
 * {
 *     // Use $timeSeries
 * }
 * ```
 */
class TimeSeriesServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * This method registers all available drivers, binds them to the Laravel container,
     * and sets up a singleton for the 'time-series' service.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/time-series.php', 'time-series'
        );

        // Register driver classes
        DriverManager::register(InfluxDBDriver::class, 'influxdb', InfluxDBConfig::class);
        $this->app->bind(InfluxDBDriver::class);
        $this->app->alias(InfluxDBDriver::class, 'time-series.influxdb');

        DriverManager::register(RRDtoolDriver::class, 'rrdtool', RRDtoolConfig::class);
        $this->app->bind(RRDtoolDriver::class);
        $this->app->alias(RRDtoolDriver::class, 'time-series.rrdtool');

        DriverManager::register(PrometheusDriver::class, 'prometheus', PrometheusConfig::class);
        $this->app->bind(PrometheusDriver::class);
        $this->app->alias(PrometheusDriver::class, 'time-series.prometheus');

        DriverManager::register(GraphiteDriver::class, 'graphite', GraphiteConfig::class);
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

            // Create the driver config using DriverManager
            $config = DriverManager::createConfig($driver, $driverConfig);

            // Create and return the driver instance
            return DriverManager::create($driver, $config);
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
