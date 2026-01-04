<?php

namespace TimeseriesPhp\Core\Contracts;

interface Driver
{
    public function getCapabilities(): DriverCapabilities;
}
