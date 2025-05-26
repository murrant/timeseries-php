<?php

namespace TimeSeriesPhp\Core;

use TimeSeriesPhp\Core\RawQueryContract;

readonly class RawQuery implements RawQueryContract
{
    public function __construct(
        private string $rawQuery,
    ) {
    }

    public function getRawQuery(): string
    {
        return $this->rawQuery;
    }
}
