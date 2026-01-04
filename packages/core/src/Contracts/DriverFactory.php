<?php


namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Runtime;

interface DriverFactory
{
    public function make(array|DriverConfig $config): Runtime;
}
