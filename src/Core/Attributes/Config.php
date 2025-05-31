<?php

namespace TimeSeriesPhp\Core\Attributes;

use Attribute;

/**
 * Attribute to mark a class as a configuration class
 *
 * This attribute is used to automatically associate a configuration class
 * with a driver class.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Config
{
    /**
     * @param  string  $driverClass  The fully qualified class name of the driver class
     */
    public function __construct(
        public readonly string $name,
        public readonly string $driverClass,
    ) {}
}
