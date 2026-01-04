<?php

namespace TimeseriesPhp\Core\Results;

use TimeseriesPhp\Core\Contracts\QueryResult;
use TimeseriesPhp\Core\Query\AST\Resolution;
use TimeseriesPhp\Core\Query\AST\TimeRange;

/**
 * @implements QueryResult<TimeSeriesQueryResult>
 */
final readonly class TimeSeriesQueryResult implements \JsonSerializable, QueryResult
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

    /**
     * @return array{series: array|array[], range: array{start: int, end: int}, resolution: int|null}
     */
    public function jsonSerialize(): array
    {
        return [
            'series' => array_map(fn (TimeSeries $series) => $series->jsonSerialize(), $this->series),
            'range' => ['start' => $this->range->start->getTimestamp(), 'end' => $this->range->end->getTimestamp()],
            'resolution' => $this->resolution->seconds,
        ];
    }
}
