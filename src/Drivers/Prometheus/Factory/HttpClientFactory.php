<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;

/**
 * Default implementation of HttpClientFactoryInterface.
 */
class HttpClientFactory implements HttpClientFactoryInterface
{
    /**
     * Create a new HTTP client.
     *
     * @return ClientInterface The HTTP client
     */
    public function create(): ClientInterface
    {
        return Psr18ClientDiscovery::find();
    }
}
