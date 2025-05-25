<?php

namespace TimeSeriesPhp\Config;

interface ConfigInterface
{
    public function get(string $key, $default = null): mixed;
}
