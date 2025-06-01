<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Factory;

use Symfony\Component\Process\InputStream;

/**
 * Factory interface for creating InputStream instances.
 */
interface InputStreamFactoryInterface
{
    /**
     * Create a new InputStream instance.
     *
     * @return InputStream The InputStream instance
     */
    public function create(): InputStream;
}
