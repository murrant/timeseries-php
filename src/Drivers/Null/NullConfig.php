<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Drivers\Null;

use TimeSeriesPhp\Core\Attributes\Config;
use TimeSeriesPhp\Core\Driver\AbstractDriverConfiguration;

/**
 * Configuration for the Null driver
 */
#[Config('null', NullDriver::class)]
class NullConfig extends AbstractDriverConfiguration
{
    public function __construct(
        bool $debug = false,
    ) {}
}
