<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use TimeseriesPhp\Core\Contracts\GraphRepository;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\GraphType;
use TimeseriesPhp\Core\Enum\MetricType;
use TimeseriesPhp\Core\Enum\VariableType;
use TimeseriesPhp\Core\Graph\GraphDefinition;
use TimeseriesPhp\Core\Graph\GraphStyle;
use TimeseriesPhp\Core\Graph\GraphVariable;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;
use TimeseriesPhp\Core\Timeseries\Labels\LabelFilter;
use TimeseriesPhp\Core\Timeseries\SeriesDefinition;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    #[\Override]
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(MetricRepository $metrics, GraphRepository $graphs): void
    {
        $bitsInMetric = new MetricIdentifier('network.port', 'bits.in', 'bps', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $bitsOutMetric = new MetricIdentifier('network.port', 'bits.out', 'bps', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);

        $metrics->register($bitsInMetric);
        $metrics->register($bitsOutMetric);

        $graphs->register(new GraphDefinition(
            id: 'host_port_bandwidth',
            title: 'Network Port Bits',
            description: null,
            variables: [
                new GraphVariable('host', VariableType::STRING),
                new GraphVariable('ifName', VariableType::STRING),
                new GraphVariable('ifIndex', VariableType::INTEGER),
            ],
            series: [
                new SeriesDefinition($bitsInMetric->key(), LabelFilter::match('ifName', 'enp10s0'), aggregation: Aggregation::RATE),
                new SeriesDefinition($bitsOutMetric->key(), LabelFilter::match('ifName', 'enp10s0'), aggregation: Aggregation::RATE),
            ],
            style: new GraphStyle(GraphType::LINE),
        ));
    }
}
