<?php

namespace TimeSeriesPhp\Utils;

class File
{
    const TAG_VALUE_SEPARATOR = '-';

    const TAG_SEPARATOR = '_';

    const DIRECTORY_SEPARATOR = '/';

    const BAD_CHARACTER_REPLACEMENT = '.';

    public static function sanitize(string $name): string
    {
        return trim(preg_replace('/[^a-zA-Z0-9\-_. ]/', '', $name));
    }

    public static function sanitizeTag(string $tag): string
    {
        return str_replace([self::TAG_SEPARATOR, self::TAG_VALUE_SEPARATOR], self::BAD_CHARACTER_REPLACEMENT, $tag);
    }
}
