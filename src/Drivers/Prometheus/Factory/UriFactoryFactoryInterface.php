<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Psr\Http\Message\UriFactoryInterface;

/**
 * Factory interface for creating URI factories.
 */
interface UriFactoryFactoryInterface
{
    /**
     * Create a new URI factory.
     *
     * @return UriFactoryInterface The URI factory
     */
    public function create(): UriFactoryInterface;
}
