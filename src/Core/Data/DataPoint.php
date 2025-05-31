<?php

namespace TimeSeriesPhp\Core\Data;

use DateTime;
use TimeSeriesPhp\Utils\Convert;

class DataPoint
{
    private readonly string $measurement;

    /**
     * @var array<string, string>
     */
    private array $tags;

    /**
     * @var array<string, ?scalar>
     */
    private array $fields;

    private readonly DateTime $timestamp;

    /**
     * @param  array<string, ?scalar>  $fields
     * @param  array<string, string>  $tags
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
     * @return array<string, ?scalar>
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
     * @param  ?scalar  $value
     * @return $this
     */
    public function addField(string $key, mixed $value): self
    {
        $this->fields[$key] = Convert::toScalar($value);

        return $this;
    }
}
