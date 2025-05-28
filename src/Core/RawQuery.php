<?php

namespace TimeSeriesPhp\Core;

readonly class RawQuery implements RawQueryInterface
{
    public function __construct(
        protected string $rawQuery,
    ) {}

    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }
}
