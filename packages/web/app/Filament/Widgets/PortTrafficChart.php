<?php

namespace App\Filament\Widgets;

use App\Actions\FetchGraphData;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use TimeseriesPhp\Core\Query\AST\TimeRange;
use TimeseriesPhp\Core\Results\TimeSeriesResult;
use TimeseriesPhp\Core\Schema\SchemaManager;

class PortTrafficChart extends ChartWidget
{
    use HasFiltersSchema;

    protected ?string $heading = 'Port Traffic';

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    #[\Override]
    protected function getData(): array
    {
        $timeSeriesResult = $this->getTimeSeriesData();

        if (! $timeSeriesResult->hasData()) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $datasets = [];
        $labels = [];

        // Build labels from the first series (all series should have same timestamps)
        if (! empty($timeSeriesResult->series)) {
            $firstSeries = $timeSeriesResult->series[0];
            foreach ($firstSeries->points as $point) {
                $labels[] = $point->timestamp * 1000; // Convert seconds to milliseconds
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
                'data' => array_map(fn ($point) => $point->value * 8, $series->points),  // FIXME transform in backend
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

    #[\Override]
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

    /**
     * Get the time series data based on the selected filter
     */
    protected function getTimeSeriesData(): TimeSeriesResult
    {
        $range = match ($this->filters['timeRange'] ?? null) {
            '1h' => TimeRange::lastHours(1),
            '6h' => TimeRange::lastHours(6),
            '24h' => TimeRange::lastDays(1),
            '7d' => TimeRange::lastDays(7),
            '30d' => TimeRange::lastDays(30),
            default => TimeRange::lastHours(6),
        };

        return app(FetchGraphData::class)->execute(
            'host_port_bandwidth',
            $this->filters['hostname'] ?? null,
            $this->filters['ifName'] ?? null,
            range: $range
        );
    }

    public function getFilters(): null
    {
        return null;
    }

    /**
     * Get available hostnames from data source
     *
     * @return string[]
     */
    protected function getAvailableHostnames(): array
    {
        $values = app(SchemaManager::class)->labels()
            ->from('network.port.bytes.in')
            ->from('network.port.bytes.out')
            ->values('host')->values;

        return array_combine($values, $values);
    }

    /**
     * Get available interface names from data source
     *
     * @return string[]
     */
    protected function getAvailableIfNames(): array
    {
        $query = app(SchemaManager::class)->labels()
            ->from('network.port.bytes.in')
            ->from('network.port.bytes.out');

        if (isset($this->filters['hostname'])) {
            $query->where('host', $this->filters['hostname']);
        }

        $values = $query->values('ifName')->values;

        return array_combine($values, $values);
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
        return ! empty($labels) ? implode(' - ', $labels) : 'Unknown';
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('timeRange')
                ->label('Time Range')
                ->options([
                    '1h' => 'Last hour',
                    '6h' => 'Last 6 hours',
                    '24h' => 'Last 24 hours',
                    '7d' => 'Last 7 days',
                    '30d' => 'Last 30 days',
                ])
                ->default('6h'),
            Select::make('hostname')
                ->label('Hostname')
                ->options(fn () => $this->getAvailableHostnames()),
            Select::make('ifName')
                ->label('Interface')
                ->options(fn () => $this->getAvailableIfNames()),
        ]);
    }
}
