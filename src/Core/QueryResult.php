<?php

namespace TimeSeriesPhp\Core;

class QueryResult
{
    private array $series;
    private array $metadata;

    public function __construct(array $series = [], array $metadata = [])
    {
        $this->series = $series;
        $this->metadata = $metadata;
    }

    public function getSeries(): array { return $this->series; }
    public function getMetadata(): array { return $this->metadata; }
    public function isEmpty(): bool { return empty($this->series); }
    public function count(): int { return count($this->series); }

    public function toArray(): array
    {
        return [
            'series' => $this->series,
            'metadata' => $this->metadata
        ];
    }
}
