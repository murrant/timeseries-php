<?php

namespace TimeSeriesPhp\Core\Attributes;

use Attribute;

/**
 * Attribute to mark a class as a driver
 *
 * This attribute is used to automatically register a class as a driver
 * with the TSDBFactory.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Driver
{
    /**
     * @param  string  $name  The name of the driver
     * @param  string|null  $configClass  The fully qualified class name of the config class (optional)
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $configClass = null,
    ) {}
}
