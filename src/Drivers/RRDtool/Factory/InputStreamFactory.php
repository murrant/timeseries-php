<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Factory;

use Symfony\Component\Process\InputStream;

/**
 * Default implementation of InputStreamFactoryInterface.
 */
class InputStreamFactory implements InputStreamFactoryInterface
{
    /**
     * Create a new InputStream instance.
     *
     * @return InputStream The InputStream instance
     */
    public function create(): InputStream
    {
        return new InputStream;
    }
}
