<?php

namespace TimeSeriesPhp\Support;

use InfluxDBDriver;
use Prometheus\PrometheusDriver;
use RRDtool\RRDtoolDriver;
use TimeSeriesPhp\Core\TSDBFactory;

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
