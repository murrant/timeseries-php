<?php

namespace TimeSeriesPhp\Core;

class QueryResult
{
    /**
     * @var array<int, array{'time': int|string, string: ?scalar}>
     */
    private array $series;

    /**
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * @param  array<int, array{'time': int|string, string: ?scalar}>  $series
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(array $series = [], array $metadata = [])
    {
        $this->series = $series;
        $this->metadata = $metadata;
    }

    /**
     * @return array<int, array{'time': int|string, string: ?scalar}>
     */
    public function getSeries(): array
    {
        return $this->series;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isEmpty(): bool
    {
        return empty($this->series);
    }

    public function count(): int
    {
        return count($this->series);
    }

    /**
     * @return array{'series': array<int, array{'time': int|string, string: ?scalar}>, 'metadata': array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'series' => $this->series,
            'metadata' => $this->metadata,
        ];
    }
}
