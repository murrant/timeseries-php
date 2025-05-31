<?php

namespace TimeSeriesPhp\Core\Query;

use TimeSeriesPhp\Contracts\Query\RawQueryInterface;

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
