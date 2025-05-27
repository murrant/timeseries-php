<?php

namespace TimeSeriesPhp\Core;

readonly class RawQuery implements RawQueryContract
{
    public function __construct(
        private string $rawQuery,
    ) {}

    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }
}
