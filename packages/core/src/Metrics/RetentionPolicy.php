<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core\Metrics;

final readonly class RetentionPolicy
{
    /**
     * @param  int  $resolution  Resolution in seconds
     * @param  int  $retention  Retention period in seconds
     */
    public function __construct(
        public int $resolution,
        public int $retention,
    ) {}

    /**
     * @param  array{resolution: int, retention: int}  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            resolution: $raw['resolution'],
            retention: $raw['retention'],
        );
    }
}
