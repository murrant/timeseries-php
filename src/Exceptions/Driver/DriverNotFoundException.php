<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Exceptions\Driver;

use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Exception thrown when a driver is not found
 */
class DriverNotFoundException extends TSDBException
{
}
