<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\DriverCapabilities;

final class NullCapabilities implements DriverCapabilities
{
    public function supportsRate(): bool
    {
        return false;
    }

    public function supportsHistogram(): bool
    {
        return false;
    }

    public function supportsLabelJoin(): bool
    {
        return false;
    }

    public function supports(string $capability): bool
    {
        return false;
    }
}
