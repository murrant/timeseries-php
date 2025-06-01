<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Default implementation of UriFactoryFactoryInterface.
 */
class UriFactoryFactory implements UriFactoryFactoryInterface
{
    /**
     * Create a new URI factory.
     *
     * @return UriFactoryInterface The URI factory
     */
    public function create(): UriFactoryInterface
    {
        return Psr17FactoryDiscovery::findUriFactory();
    }
}
