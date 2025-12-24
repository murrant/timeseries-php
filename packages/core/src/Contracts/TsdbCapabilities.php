<?php

namespace TimeseriesPhp\Core\Contracts;

interface TsdbCapabilities
{
    public function supportsRate(): bool;

    public function supportsHistogram(): bool;

    public function supportsLabelJoin(): bool;

    public function supports(string $capability): bool;
}
