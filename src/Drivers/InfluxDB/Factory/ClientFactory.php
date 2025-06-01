<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Factory;

use InfluxDB2\Client;

/**
 * Default implementation of ClientFactoryInterface.
 */
class ClientFactory implements ClientFactoryInterface
{
    /**
     * Create a new InfluxDB client.
     *
     * @param  array<string, mixed>  $config  The client configuration
     * @return Client The InfluxDB client
     */
    public function create(array $config): Client
    {
        return new Client($config);
    }
}
