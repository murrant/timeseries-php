<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\TsdbConfig;

class RrdConfig implements TsdbConfig
{
    public static function fromArray(array $config): TsdbConfig
    {
        return new self;
    }
}
