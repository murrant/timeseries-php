<?php

namespace TimeSeriesPhp\Utils;

class Convert
{
    public static function toNumber(float|int|string|null $value): int|float|null
    {
        if ($value === null || (is_string($value) && ! is_numeric(trim($value)))) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return $value;
        }
        $floatValue = (float) trim($value);

        return (floor($floatValue) == $floatValue && $floatValue <= PHP_INT_MAX && $floatValue >= PHP_INT_MIN)
            ? (int) $floatValue
            : $floatValue;
    }

    public static function toScalar(mixed $value): float|int|bool|string|null
    {
        if (is_array($value)) {
            return isset($value[0]) && is_scalar($value[0]) ? $value[0] : null;
        }

        return is_scalar($value) ? $value : null;
    }

    public static function toString(mixed $value): string
    {
        return (string) (is_scalar($value) ? $value : self::toScalar($value));
    }
}
