<?php

namespace TimeSeriesPhp\Contracts\Query;

interface RawQueryInterface
{
    public function getRawQuery(): string;
}
