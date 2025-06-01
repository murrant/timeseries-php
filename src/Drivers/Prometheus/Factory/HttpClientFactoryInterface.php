<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Psr\Http\Client\ClientInterface;

/**
 * Factory interface for creating HTTP clients.
 */
interface HttpClientFactoryInterface
{
    /**
     * Create a new HTTP client.
     *
     * @return ClientInterface The HTTP client
     */
    public function create(): ClientInterface;
}
