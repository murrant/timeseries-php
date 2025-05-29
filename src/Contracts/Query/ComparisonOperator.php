<?php

namespace TimeSeriesPhp\Contracts\Query;

enum ComparisonOperator: string
{
    case EQUALS = '=';
    case SAME = '==';
    case NOT_EQUALS = '!=';
    case NOT_EQUALS_ALT = '<>';
    case GREATER_THAN = '>';
    case GREATER_THAN_OR_EQUALS = '>=';
    case LESS_THAN = '<';
    case LESS_THAN_OR_EQUALS = '<=';
    case LIKE = 'LIKE';
    case REGEX = 'REGEX';
    case NOT_REGEX = 'NOT REGEX';
    case IN = 'IN';
    case NOT_IN = 'NOT IN';
    case BETWEEN = 'BETWEEN';

    /**
     * Get all available operator values as strings
     *
     * @return array<string> Array of operator string values
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * Check if this operator requires an array value
     *
     * @return bool True if the operator requires an array value
     */
    public function requiresArrayValue(): bool
    {
        return match ($this) {
            self::IN, self::NOT_IN, self::BETWEEN => true,
            default => false,
        };
    }

    /**
     * Map SQL-like operators to Flux operators
     *
     * @return string The Flux operator
     */
    public function toFluxOperator(): string
    {
        return match ($this) {
            self::EQUALS => '==',
            self::NOT_EQUALS, self::NOT_EQUALS_ALT => '!=',
            self::GREATER_THAN => '>',
            self::GREATER_THAN_OR_EQUALS => '>=',
            self::LESS_THAN => '<',
            self::LESS_THAN_OR_EQUALS => '<=',
            self::LIKE => '=~',
            default => $this->value,
        };
    }
}
