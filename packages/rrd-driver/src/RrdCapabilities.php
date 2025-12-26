<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\TsdbCapabilities;

class RrdCapabilities implements TsdbCapabilities
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
}
