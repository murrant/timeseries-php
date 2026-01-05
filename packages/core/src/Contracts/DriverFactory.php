<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Runtime;

interface DriverFactory
{
    /**
     * @return Runtime Runtime must contain a concrete DriverConfig (not array)
     */
    public function make(array|DriverConfig $config): Runtime;
}
