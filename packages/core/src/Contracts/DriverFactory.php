<?php


namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Runtime;

interface DriverFactory
{
    /**
     * @param array|DriverConfig $config
     * @return Runtime Runtime must contain a concrete DriverConfig (not array)
     */
    public function make(array|DriverConfig $config): Runtime;
}
