<?php


namespace TimeseriesPhp\Core\Contracts;

interface TsdbConfig
{
    public static function fromArray(array $config): self;
}
