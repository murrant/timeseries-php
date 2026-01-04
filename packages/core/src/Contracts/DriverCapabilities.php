<?php

namespace TimeseriesPhp\Core\Contracts;

interface DriverCapabilities
{
    public function supports(string $capability): bool;
}
