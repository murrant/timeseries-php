<?php

namespace TimeseriesPhp\Core\Contracts;

interface Capabilities
{
    public function supportsRate(): bool;

    public function supportsHistogram(): bool;

    public function supportsLabelJoin(): bool;
}
