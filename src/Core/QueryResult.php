<?php

namespace TimeSeriesPhp\Core;

class QueryResult
{
    /**
     * @var array<int|string, mixed>
     */
    private array $series;

    /**
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * @param  array<int|string, mixed>  $series
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(array $series = [], array $metadata = [])
    {
        $this->series = $series;
        $this->metadata = $metadata;
    }

    /**
     * @return array<int|string, mixed>
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
     * @return array{'series': array<int|string, mixed>, 'metadata': array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'series' => $this->series,
            'metadata' => $this->metadata,
        ];
    }
}
