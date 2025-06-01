<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * Default implementation of RequestFactoryFactoryInterface.
 */
class RequestFactoryFactory implements RequestFactoryFactoryInterface
{
    /**
     * Create a new request factory.
     *
     * @return RequestFactoryInterface The request factory
     */
    public function create(): RequestFactoryInterface
    {
        return Psr17FactoryDiscovery::findRequestFactory();
    }
}
