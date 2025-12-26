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
        $bytesInMetric = new MetricIdentifier('network.port', 'bytes.in', 'bytes', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $bytesOutMetric = new MetricIdentifier('network.port', 'bytes.out', 'bytes', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $packetsInMetric = new MetricIdentifier('network.port', 'packets.in', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $packetsOutMetric = new MetricIdentifier('network.port', 'packets.out', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $errorsInMetric = new MetricIdentifier('network.port', 'errors.in', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $errorsOutMetric = new MetricIdentifier('network.port', 'errors.out', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $droppedInMetric = new MetricIdentifier('network.port', 'dropped.in', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $droppedOutMetric = new MetricIdentifier('network.port', 'dropped.out', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $overrunMetric = new MetricIdentifier('network.port', 'overrun', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $multicastMetric = new MetricIdentifier('network.port', 'multicast', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $carrierMetric = new MetricIdentifier('network.port', 'carrier', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);
        $collisionsMetric = new MetricIdentifier('network.port', 'collisions', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::RATE, Aggregation::SUM]);

        $metrics->register($bitsInMetric);
        $metrics->register($bitsOutMetric);
        $metrics->register($bytesInMetric);
        $metrics->register($bytesOutMetric);
        $metrics->register($packetsInMetric);
        $metrics->register($packetsOutMetric);
        $metrics->register($errorsInMetric);
        $metrics->register($errorsOutMetric);
        $metrics->register($droppedInMetric);
        $metrics->register($droppedOutMetric);
        $metrics->register($overrunMetric);
        $metrics->register($multicastMetric);
        $metrics->register($carrierMetric);
        $metrics->register($collisionsMetric);

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
                new SeriesDefinition($bytesInMetric->key(), LabelFilter::match('ifName', 'enp10s0'), aggregation: Aggregation::RATE),
                new SeriesDefinition($bytesOutMetric->key(), LabelFilter::match('ifName', 'enp10s0'), aggregation: Aggregation::RATE),
            ],
            style: new GraphStyle(GraphType::LINE),
        ));
    }
}
