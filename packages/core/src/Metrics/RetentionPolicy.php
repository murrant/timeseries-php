<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core\Metrics;

use TimeseriesPhp\Core\Enum\Aggregation;

final readonly class RetentionPolicy
{
    /**
     * @param  string  $name  e.g., "hourly_rollup"
     * @param  int  $resolution  Resolution in seconds (e.g., 3600)
     * @param  int  $retention  Total time to keep data in seconds (e.g., 86400 * 30)
     * @param  Aggregation  $aggregator  The method used to downsample (sum, avg, max, etc.)
     */
    public function __construct(
        public string $name,
        public int $resolution,
        public int $retention,
        public Aggregation $aggregator = Aggregation::Average,
    ) {
        if ($this->resolution <= 0 || $this->retention <= 0) {
            throw new \InvalidArgumentException('Durations must be positive.');
        }

        if ($this->retention < $this->resolution) {
            throw new \InvalidArgumentException('Retention cannot be shorter than resolution.');
        }
    }

    public function getPointCount(): int
    {
        return (int) ($this->retention / $this->resolution);
    }

    /**
     * @param  array{name: string, resolution: int, retention: int, aggregator?: string}  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            name: $raw['name'],
            resolution: $raw['resolution'],
            retention: $raw['retention'],
            aggregator: Aggregation::tryFrom($raw['aggregator'] ?? 'avg') ?? Aggregation::Average,
        );
    }
}
