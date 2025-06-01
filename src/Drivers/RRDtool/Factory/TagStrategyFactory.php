<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Factory;

use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyInterface;
use TimeSeriesPhp\Exceptions\Driver\ConnectionException;

/**
 * Default implementation of TagStrategyFactoryInterface.
 */
class TagStrategyFactory implements TagStrategyFactoryInterface
{
    /**
     * Create a new RRDTagStrategyInterface instance.
     *
     * @param  string  $strategyClass  The class name of the tag strategy
     * @param  string  $rrdDir  The RRD directory
     * @return RRDTagStrategyInterface The RRDTagStrategyInterface instance
     *
     * @throws ConnectionException If the strategy class is invalid
     */
    public function create(string $strategyClass, string $rrdDir): RRDTagStrategyInterface
    {
        $instance = new $strategyClass($rrdDir);

        if (! $instance instanceof RRDTagStrategyInterface) {
            throw new ConnectionException('Invalid tag strategy class, must implement RRDTagStrategyInterface');
        }

        return $instance;
    }
}
