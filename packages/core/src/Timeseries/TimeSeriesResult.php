<?php

namespace TimeseriesPhp\Core\Timeseries;

use TimeseriesPhp\Core\Time\TimeRange;

final readonly class TimeSeriesResult implements \JsonSerializable
{
    /**
     * @param  TimeSeries[]  $series
     */
    public function __construct(
        public array $series,
        public TimeRange $range,
        public Resolution $resolution,
    ) {}

    public function hasData(): bool
    {
        return ! empty($this->series);
    }

    public function jsonSerialize(): array
    {
        return [
            'series' => array_map(fn (TimeSeries $series) => $series->jsonSerialize(), $this->series),
            'range' => ['start' => $this->range->start->getTimestamp(), 'end' => $this->range->end->getTimestamp()],
            'resolution' => $this->resolution->seconds,
        ];
    }
}
