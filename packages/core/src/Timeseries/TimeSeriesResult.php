<?php

namespace TimeseriesPhp\Core\Timeseries;

use TimeseriesPhp\Core\Time\TimeRange;

final readonly class TimeSeriesResult
{
    /**
     * @param  TimeSeries[]  $series
     */
    public function __construct(
        public array $series,
        public TimeRange $range,
        public Resolution $resolution,
    ) {}

    /**
     * @return string[] FIXME is this type correct?
     */
    public function allLabels(): array
    {
        return []; // TODO
    }

    public function hasData(): bool
    {
        return ! empty($this->series);
    }
}
