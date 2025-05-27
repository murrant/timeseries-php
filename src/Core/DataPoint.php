<?php

namespace TimeSeriesPhp\Core;

use DateTime;

class DataPoint
{
    private string $measurement;

    private array $tags;

    private array $fields;

    private DateTime $timestamp;

    public function __construct(
        string $measurement,
        array $fields,
        array $tags = [],
        ?DateTime $timestamp = null
    ) {
        $this->measurement = $measurement;
        $this->fields = $fields;
        $this->tags = $tags;
        $this->timestamp = $timestamp ?? new DateTime;
    }

    public function getMeasurement(): string
    {
        return $this->measurement;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function getTimestamp(): DateTime
    {
        return $this->timestamp;
    }

    public function addTag(string $key, string $value): self
    {
        $this->tags[$key] = $value;

        return $this;
    }

    public function addField(string $key, $value): self
    {
        $this->fields[$key] = $value;

        return $this;
    }
}
