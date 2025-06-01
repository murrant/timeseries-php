<?php

namespace TimeSeriesPhp\Drivers\InfluxDB\Factory;

use InfluxDB2\Client;

/**
 * Factory interface for creating InfluxDB clients.
 */
interface ClientFactoryInterface
{
    /**
     * Create a new InfluxDB client.
     *
     * @param  array<string, mixed>  $config  The client configuration
     * @return Client The InfluxDB client
     */
    public function create(array $config): Client;
}
