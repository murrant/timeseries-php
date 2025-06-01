<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Factory;

use Symfony\Component\Process\Process;

/**
 * Factory interface for creating Process instances.
 */
interface ProcessFactoryInterface
{
    /**
     * Create a new Process instance.
     *
     * @param  array<string>  $command  The command to run
     * @return Process The Process instance
     */
    public function create(array $command): Process;
}
