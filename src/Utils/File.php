<?php

namespace TimeSeriesPhp\Utils;

class File
{
    const TAG_VALUE_SEPARATOR = '-';

    const TAG_SEPARATOR = '_';

    const BAD_CHARACTER_REPLACEMENT = '.';

    public static function sanitize(string $name): string
    {
        $replaced = preg_replace('/[^a-zA-Z0-9\-_. ]/', '', $name);

        return trim($replaced ?? '');
    }

    public static function sanitizeTagValue(string $tag): string
    {
        return str_replace([self::TAG_SEPARATOR, self::TAG_VALUE_SEPARATOR], self::BAD_CHARACTER_REPLACEMENT, $tag);
    }
}
