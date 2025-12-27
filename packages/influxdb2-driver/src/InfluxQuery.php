<?php

namespace TimeseriesPhp\Driver\InfluxDB2;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;

class InfluxQuery implements \Stringable, CompiledQuery
{
    public function __construct(
        public readonly array $flux,
        public readonly TimeRange $range,
        public readonly Resolution $resolution,
    ) {}

    public function __toString(): string
    {
        return implode("\n", $this->flux);
    }
}
