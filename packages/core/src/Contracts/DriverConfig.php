<?php

namespace TimeseriesPhp\Core\Contracts;

interface DriverConfig
{
    public static function fromArray(array $config): self;
}
