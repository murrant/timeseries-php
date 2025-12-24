<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\TsdbCapabilities;

final class NullCapabilities implements TsdbCapabilities
{
    public function supportsRate(): bool
    {
        return true;
    }

    public function supportsHistogram(): bool
    {
        return true;
    }

    public function supportsLabelJoin(): bool
    {
        return true;
    }

    public function supports(string $capability): bool
    {
        return true;
    }
}
