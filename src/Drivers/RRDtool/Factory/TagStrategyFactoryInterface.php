<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Factory;

use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;

/**
 * Factory interface for creating RRDTagStrategyInterface instances.
 */
interface TagStrategyFactoryInterface
{
    /**
     * Create a new RRDTagStrategyInterface instance.
     *
     * @param  string  $strategyClass  The class name of the tag strategy
     * @param  string  $rrdDir  The RRD directory
     * @return RRDTagStrategyInterface The RRDTagStrategyInterface instance
     */
    public function create(string $strategyClass, string $rrdDir): RRDTagStrategyInterface;
}
