<?php

namespace TimeseriesPhp\Driver\RRD;

use TimeseriesPhp\Core\Contracts\TsdbCapabilities;

class RrdTsdbCapabilities implements TsdbCapabilities
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
