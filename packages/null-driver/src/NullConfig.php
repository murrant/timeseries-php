<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\TsdbConfig;

class NullConfig implements TsdbConfig
{
    public static function fromArray(array $config): self
    {
        return new self;
    }
}
