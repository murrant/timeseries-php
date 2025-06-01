<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Psr\Http\Message\RequestFactoryInterface;

/**
 * Factory interface for creating request factories.
 */
interface RequestFactoryFactoryInterface
{
    /**
     * Create a new request factory.
     *
     * @return RequestFactoryInterface The request factory
     */
    public function create(): RequestFactoryInterface;
}
