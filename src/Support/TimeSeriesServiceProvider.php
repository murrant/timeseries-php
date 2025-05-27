<?php

namespace TimeSeriesPhp\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TimeSeriesPhp\Config\ConfigFactory;
use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBConfig;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;

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

        // Register the time-series singleton
        $this->app->singleton('time-series', function (Application $app) {
            $driver = $app['config']->get('time-series.driver');
            $driverConfig = $app['config']->get('time-series.drivers.'.$driver, []);

            // Create the appropriate config object based on driver
            $configClass = match ($driver) {
                'influxdb' => InfluxDBConfig::class,
                'rrdtool' => RRDtoolConfig::class,
                default => null,
            };

            if ($configClass) {
                $config = new $configClass($driverConfig);
            } else {
                // For drivers without specific config classes (like Prometheus)
                $config = ConfigFactory::create('database', $driverConfig);
            }

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
        $this->publishes([
            __DIR__.'/../../config/time-series.php' => config_path('time-series.php'),
        ], 'time-series-config');
    }
}
