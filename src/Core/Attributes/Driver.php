<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core\Attributes;

use Attribute;

/**
 * Attribute to mark a class as a driver
 *
 * This attribute is used to automatically register a class as a driver
 */
#[Attribute(Attribute::TARGET_CLASS)]
class Driver
{
    /**
     * @param  string  $name  The name of the driver
     * @param  string|null  $queryBuilderClass  The fully qualified class name of the query builder class (optional)
     * @param  string|null  $configClass  The fully qualified class name of the config class (optional)
     * @param  string|null  $schemaManagerClass  The fully qualified class name of the schema manager class (optional)
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $queryBuilderClass = null,
        public readonly ?string $configClass = null,
        public readonly ?string $schemaManagerClass = null,
    ) {}
}
