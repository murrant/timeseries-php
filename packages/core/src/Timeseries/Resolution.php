<?php

namespace TimeseriesPhp\Core\Timeseries;

final readonly class Resolution
{
    public function __construct(
        public ?int $seconds = null,
    ) {}

    public static function auto(): self
    {
        return new self;
    }

    public static function minutes(int $minutes = 1): self
    {
        return new self($minutes * 60);
    }

    public static function fromArray(array $raw): self
    {
        return new self($raw['seconds'] ?? null);
    }
}
