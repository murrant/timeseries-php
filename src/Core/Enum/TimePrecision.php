<?php

namespace TimeSeriesPhp\Core\Enum;

enum TimePrecision: string
{
    case MS = 'ms';
    case S = 's';
    case US = 'us';
    case NS = 'ns';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(fn (self $precision) => $precision->value, self::cases());
    }
}
