<?php

namespace TimeseriesPhp\Core\Contracts;

interface DriverConfig
{
    /**
     * @param array<string, mixed>|DriverConfig $config
     */
    public static function make(array|DriverConfig $config = []): self;
}
