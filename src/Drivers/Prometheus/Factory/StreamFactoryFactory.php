<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Default implementation of StreamFactoryFactoryInterface.
 */
class StreamFactoryFactory implements StreamFactoryFactoryInterface
{
    /**
     * Create a new stream factory.
     *
     * @return StreamFactoryInterface The stream factory
     */
    public function create(): StreamFactoryInterface
    {
        return Psr17FactoryDiscovery::findStreamFactory();
    }
}
