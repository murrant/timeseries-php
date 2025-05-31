<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core\Attributes;

use Attribute;

/**
 * Attribute to mark a class as a cache driver
 *
 * This attribute is used to automatically register a class as a cache driver
 */
#[Attribute(Attribute::TARGET_CLASS)]
class CacheDriver
{
    /**
     * @param  string  $name  The name of the cache driver
     * @param  string|null  $configClass  The fully qualified class name of the config class (optional)
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $configClass = null,
    ) {}
}
