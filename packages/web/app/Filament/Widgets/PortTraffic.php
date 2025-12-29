<?php

namespace App\Filament\Widgets;

use App\Actions\FetchGraphData;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

class PortTraffic extends ChartWidget
{
    use HasFiltersSchema;

    protected ?string $heading = 'Port Traffic';
    protected static ?int $sort = 2;
    protected ?string $pollingInterval = '60s';
    protected int | string | array $columnSpan = 'full';
    public ?string $filter = '24h';

    protected function getData(): array
    {
        $timeSeriesResult = $this->getTimeSeriesData();

        if (!$timeSeriesResult->hasData()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $datasets = [];
        $labels = [];

        // Build labels from the first series (all series should have same timestamps)
        if (!empty($timeSeriesResult->series)) {
            $firstSeries = $timeSeriesResult->series[0];
            foreach ($firstSeries->points as $point) {
                $labels[] = $point->timestamp * 1000;
            }
        }

        // Color palette for different ports
        $colors = [
            'rgba(59, 130, 246, 0.8)',  // blue
            'rgba(16, 185, 129, 0.8)',  // green
            'rgba(251, 146, 60, 0.8)',  // orange
            'rgba(139, 92, 246, 0.8)',  // purple
            'rgba(236, 72, 153, 0.8)',  // pink
            'rgba(234, 179, 8, 0.8)',   // yellow
        ];

        foreach ($timeSeriesResult->series as $index => $series) {
            $portLabel = $this->extractPortLabel($series->labels);
            $color = $colors[$index % count($colors)];

            $datasets[] = [
                'label' => $portLabel,
                'data' => array_map(fn($point) => $point->value * 8, $series->points),  // FIXME transform in backend
                'borderColor' => $color,
                'backgroundColor' => str_replace('0.8', '0.1', $color),
                'fill' => true,
                'tension' => 0.4,
                'pointRadius' => 0,
                'pointHoverRadius' => 6,
                'pointHoverBackgroundColor' => $color,
                'pointHoverBorderColor' => '#fff',
                'pointHoverBorderWidth' => 2,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
        {
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: (context) => {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                const value = context.parsed.y;
                                const units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
                                let unitIndex = 0;
                                let formattedValue = value;

                                while (formattedValue >= 1000 && unitIndex < units.length - 1) {
                                    formattedValue /= 1000;
                                    unitIndex++;
                                }

                                label += formattedValue.toFixed(2) + ' ' + units[unitIndex];
                            }
                            return label;
                        }
                    },
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Traffic (bps)',
                    },
                    ticks: {
                        callback: (value) => {
                            const units = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];
                            let unitIndex = 0;
                            let formattedValue = value;

                            while (formattedValue >= 1000 && unitIndex < units.length - 1) {
                                formattedValue /= 1000;
                                unitIndex++;
                            }

                            return formattedValue.toFixed(2) + ' ' + units[unitIndex];
                        },
                    },
                },
                x: {
                    type: 'time',
                    time: {
                        tooltipFormat: 'MMM dd, yyyy HH:mm',
                        displayFormats: {
                            hour: 'HH:mm',
                            day: 'MMM dd',
                            week: 'MMM dd',
                            month: 'MMM yyyy'
                        }
                    },
                    title: {
                        display: true,
                        text: 'Time',
                    },
                    ticks: {
                        maxRotation: 0,
                        autoSkipPadding: 20,
                    }
                },
            },
            interaction: {
                mode: 'nearest',
                axis: 'x',
                intersect: false,
            },
        }
        JS);
    }

    protected function getFilters(): ?array
    {
        return [
            '1h' => 'Last hour',
            '6h' => 'Last 6 hours',
            '24h' => 'Last 24 hours',
            '7d' => 'Last 7 days',
            '30d' => 'Last 30 days',
        ];
    }

    /**
     * Get the time series data based on the selected filter
     */
    protected function getTimeSeriesData(): TimeSeriesResult
    {
        $range = match($this->filter) {
            '1h' => TimeRange::lastHours(1),
            '6h' => TimeRange::lastHours(6),
            '24h' => TimeRange::lastDays(1),
            '7d' => TimeRange::lastDays(7),
            '30d' => TimeRange::lastDays(30),
            default => TimeRange::lastHours(6),
        };

        return $timeSeriesResult = app(FetchGraphData::class)->execute('host_port_bandwidth', range: $range);
    }

    /**
     * Extract port label from series labels array
     */
    protected function extractPortLabel(array $labels): string
    {
        if (isset($labels['ifName']) && isset($labels['host'])) {
            $ifIndex = isset($labels['ifIndex']) ? " ({$labels['ifIndex']})" : '';

            return "{$labels['host']}: {$labels['ifName']}$ifIndex";
        }

        // Fallback to first label or join all labels
        return !empty($labels) ? implode(' - ', $labels) : 'Unknown';
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('startDate')
                ->default(now()->subDays(30)),
            DatePicker::make('endDate')
                ->default(now()),
        ]);
    }
}
