<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core\Attributes;

use Attribute;

/**
 * Mark the driver this class is for, usually applied to the factory class
 * UNUSED currently, this is unused.
 */
#[Attribute(Attribute::TARGET_CLASS)]
class TimeseriesPhpDriver
{
    public function __construct(
        public string $name,
    ) {}
}
