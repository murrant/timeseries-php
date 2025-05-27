<?php

namespace TimeSeriesPhp\Utils;

class File
{
    public static function sanitize(string $name): string
    {
        return trim(preg_replace('/[^a-zA-Z0-9\-_. ]/', '', $name));
    }
}
