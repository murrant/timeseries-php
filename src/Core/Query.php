<?php

namespace TimeSeriesPhp\Core;

use DateTime;

class Query
{
    private string $measurement;
    private array $fields = ['*'];
    private array $tags = [];
    private ?DateTime $startTime = null;
    private ?DateTime $endTime = null;
    private array $groupBy = [];
    private ?string $aggregation = null;
    private ?string $interval = null;
    private ?int $limit = null;
    private array $orderBy = [];

    public function __construct(string $measurement)
    {
        $this->measurement = $measurement;
    }

    public function select(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    public function where(string $tag, string $value): self
    {
        $this->tags[$tag] = $value;
        return $this;
    }

    public function timeRange(DateTime $start, DateTime $end): self
    {
        $this->startTime = $start;
        $this->endTime = $end;
        return $this;
    }

    public function groupBy(array $tags): self
    {
        $this->groupBy = $tags;
        return $this;
    }

    public function aggregate(string $function, ?string $interval = null): self
    {
        $this->aggregation = $function;
        $this->interval = $interval;
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function orderBy(string $field, string $direction = 'ASC'): self
    {
        $this->orderBy[$field] = $direction;
        return $this;
    }

    // Getters
    public function getMeasurement(): string { return $this->measurement; }
    public function getFields(): array { return $this->fields; }
    public function getTags(): array { return $this->tags; }
    public function getStartTime(): ?DateTime { return $this->startTime; }
    public function getEndTime(): ?DateTime { return $this->endTime; }
    public function getGroupBy(): array { return $this->groupBy; }
    public function getAggregation(): ?string { return $this->aggregation; }
    public function getInterval(): ?string { return $this->interval; }
    public function getLimit(): ?int { return $this->limit; }
    public function getOrderBy(): array { return $this->orderBy; }
}
