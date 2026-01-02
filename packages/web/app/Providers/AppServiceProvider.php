<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use TimeseriesPhp\Core\Contracts\MetricRepository;
use TimeseriesPhp\Core\Enum\Aggregation;
use TimeseriesPhp\Core\Enum\GraphType;
use TimeseriesPhp\Core\Enum\MetricType;
use TimeseriesPhp\Core\Graph\GraphDefinition;
use TimeseriesPhp\Core\Graph\GraphStyle;
use TimeseriesPhp\Core\Graph\GraphVariable;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;

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
    public function boot(MetricRepository $metrics): void
    {
        $bitsInMetric = new MetricIdentifier('network.port', 'bits.in', 'bps', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $bitsOutMetric = new MetricIdentifier('network.port', 'bits.out', 'bps', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $bytesInMetric = new MetricIdentifier('network.port', 'bytes.in', 'bytes', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $bytesOutMetric = new MetricIdentifier('network.port', 'bytes.out', 'bytes', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $packetsInMetric = new MetricIdentifier('network.port', 'packets.in', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $packetsOutMetric = new MetricIdentifier('network.port', 'packets.out', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $errorsInMetric = new MetricIdentifier('network.port', 'errors.in', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $errorsOutMetric = new MetricIdentifier('network.port', 'errors.out', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $droppedInMetric = new MetricIdentifier('network.port', 'dropped.in', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $droppedOutMetric = new MetricIdentifier('network.port', 'dropped.out', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $overrunMetric = new MetricIdentifier('network.port', 'overrun', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $multicastMetric = new MetricIdentifier('network.port', 'multicast', 'packets', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $carrierMetric = new MetricIdentifier('network.port', 'carrier', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);
        $collisionsMetric = new MetricIdentifier('network.port', 'collisions', 'errors', MetricType::COUNTER, ['host', 'ifName', 'ifIndex'], [Aggregation::Rate, Aggregation::Sum]);

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

        //        $graphs->register(new GraphDefinition(
        //            id: 'host_port_bandwidth',
        //            title: 'Network Port Bits',
        //            description: null,
        //            variables: [
        //                new GraphVariable('host', VariableType::STRING),
        //                new GraphVariable('ifName', VariableType::STRING),
        //                new GraphVariable('ifIndex', VariableType::INTEGER),
        //            ],
        //            series: [
        //                new SeriesDefinition($bytesInMetric->key(), aggregation: Aggregation::RATE),
        //                new SeriesDefinition($bytesOutMetric->key(), aggregation: Aggregation::RATE),
        //            ],
        //            style: new GraphStyle(GraphType::LINE),
        //        ));
    }
}
