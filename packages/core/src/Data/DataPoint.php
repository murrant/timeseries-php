<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core\Data;

use DateTimeImmutable;
use DateTimeInterface;
use JsonSerializable;

final readonly class DataPoint implements JsonSerializable
{
    /**
     * @param  string  $measurement  The measurement/metric name
     * @param  float|int|string|bool|null  $value  The value to store
     * @param  DateTimeImmutable  $timestamp  The timestamp for this data point
     * @param  array<string, string>  $tags  Indexed dimensions (e.g., host, region, environment)
     * @param  array<string, float|int|string|bool|null>  $fields  Additional fields/values
     * @param  array<string, mixed>  $metadata  Extra metadata for specific database implementations
     */
    public function __construct(
        public string $measurement,
        public float|int|string|bool|null $value,
        public DateTimeImmutable $timestamp,
        public array $tags = [],
        public array $fields = [],
        public array $metadata = [],
    ) {}

    /**
     * Create a new instance with the current timestamp
     */
    public static function now(
        string $measurement,
        float|int|string|bool|null $value,
        array $tags = [],
        array $fields = [],
        array $metadata = [],
    ): self {
        return new self(
            measurement: $measurement,
            value: $value,
            timestamp: new DateTimeImmutable,
            tags: $tags,
            fields: $fields,
            metadata: $metadata,
        );
    }

    /**
     * Add or override metadata
     */
    public function withMetadata(string $key, mixed $value): self
    {
        return new self(
            measurement: $this->measurement,
            value: $this->value,
            timestamp: $this->timestamp,
            tags: $this->tags,
            fields: $this->fields,
            metadata: [...$this->metadata, $key => $value],
        );
    }

    /**
     * Add or override a tag
     */
    public function withTag(string $key, string $value): self
    {
        return new self(
            measurement: $this->measurement,
            value: $this->value,
            timestamp: $this->timestamp,
            tags: [...$this->tags, $key => $value],
            fields: $this->fields,
            metadata: $this->metadata,
        );
    }

    /**
     * Add or override a field
     */
    public function withField(string $key, float|int|string|bool|null $value): self
    {
        return new self(
            measurement: $this->measurement,
            value: $this->value,
            timestamp: $this->timestamp,
            tags: $this->tags,
            fields: [...$this->fields, $key => $value],
            metadata: $this->metadata,
        );
    }

    /**
     * Get timestamp as Unix timestamp (seconds)
     */
    public function getUnixTimestamp(): int
    {
        return $this->timestamp->getTimestamp();
    }

    /**
     * Check if specific metadata exists
     */
    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Get specific metadata value
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Serialize to array
     */
    public function toArray(): array
    {
        return [
            'measurement' => $this->measurement,
            'value' => $this->value,
            'timestamp' => $this->timestamp->format(DateTimeInterface::ATOM),
            'timestamp_unix' => $this->getUnixTimestamp(),
            'tags' => $this->tags,
            'fields' => $this->fields,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * JSON serialization
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
