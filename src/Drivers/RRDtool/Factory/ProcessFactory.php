<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Factory;

use Symfony\Component\Process\Process;

/**
 * Default implementation of ProcessFactoryInterface.
 */
class ProcessFactory implements ProcessFactoryInterface
{
    /**
     * Create a new Process instance.
     *
     * @param  array<string>  $command  The command to run
     * @return Process The Process instance
     */
    public function create(array $command): Process
    {
        return new Process($command);
    }
}
