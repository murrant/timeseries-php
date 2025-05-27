<?php

namespace TimeSeriesPhp\Core;

use DateTime;

class DataPoint
{
    private string $measurement;

    /**
     * @var array<string, string>
     */
    private array $tags;

    /**
     * @var array<string, mixed>
     */
    private array $fields;

    private DateTime $timestamp;

    /**
     * @param string $measurement
     * @param array<string, mixed> $fields
     * @param array<string, string> $tags
     * @param DateTime|null $timestamp
     */
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

    /**
     * @return array<string, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addField(string $key, mixed $value): self
    {
        $this->fields[$key] = $value;

        return $this;
    }
}
