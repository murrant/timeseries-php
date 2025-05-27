<?php

namespace TimeSeriesPhp\Core;

readonly class RawQuery implements RawQueryContract
{
    public function __construct(
        protected string $rawQuery,
    ) {}

    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }
}
