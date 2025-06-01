<?php

namespace TimeSeriesPhp\Drivers\Prometheus\Factory;

use Psr\Http\Message\StreamFactoryInterface;

/**
 * Factory interface for creating stream factories.
 */
interface StreamFactoryFactoryInterface
{
    /**
     * Create a new stream factory.
     *
     * @return StreamFactoryInterface The stream factory
     */
    public function create(): StreamFactoryInterface;
}
