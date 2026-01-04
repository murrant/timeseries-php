<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\DriverConfig;

class NullConfig implements DriverConfig
{
    public static function fromArray(array $config): self
    {
        return new self;
    }
}
