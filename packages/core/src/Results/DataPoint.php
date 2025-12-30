<?php

namespace TimeseriesPhp\Core\Results;

final readonly class DataPoint
{
    public function __construct(
        public int $timestamp, // unix seconds
        public float|int|null $value,
    ) {}
}
