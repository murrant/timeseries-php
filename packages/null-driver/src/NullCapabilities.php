<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\TsdbCapabilities;

final class NullCapabilities implements TsdbCapabilities
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
