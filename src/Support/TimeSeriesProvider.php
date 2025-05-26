<?php

namespace TimeSeriesPhp\Support;

use Prometheus\PrometheusDriver;
use TimeSeriesPhp\Core\TSDBFactory;
use TimeSeriesPhp\Drivers\InfluxDB\InfluxDBDriver;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolDriver;

class TimeSeriesProvider
{
    public function register(): void
    {
        TSDBFactory::registerDriver('influxdb', InfluxDBDriver::class);
        TSDBFactory::registerDriver('prometheus', PrometheusDriver::class);
        TSDBFactory::registerDriver('rrdtool', RRDtoolDriver::class);
    }
    
    public function boot(): void
    {
        
    }
}
