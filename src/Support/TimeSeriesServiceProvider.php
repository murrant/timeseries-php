<?php

namespace TimeSeriesPhp\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\Prometheus\PrometheusDriver;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;

class TimeSeriesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->alias(InfluxDBDriver::class, 'time-series.influxdb');
        $this->app->alias(PrometheusDriver::class, 'time-series.prometheus');
        $this->app->alias(RRDtoolDriver::class, 'time-series.rrdtool');

        $this->app->singleton('time-series', function (Application $app) {
            $driver = $app['config']->get('time-series.driver');
            $config = $app['config']->get('time-series.drivers.'.$driver);

            return $app->make('time-series.'.$driver, [$config]);
        });


    }
    
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/time-series.php' => config_path('time-series.php'),
        ]);
    }
}
