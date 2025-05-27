<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Exceptions\TSDBException;

readonly class TagCondition
{
    public function __construct(
        public string $tag,
        public string $operator,
        public mixed  $value,
        public string $condition = 'AND',
    ){
    }

    public function matches(string $value): bool
    {
        return match($this->operator) {
            '=' => $this->value === $value,
            'IN' => in_array($value, $this->value),
            'REGEX' => preg_match($this->value, $value),
            'NOT IN' => !in_array($value, $this->value),
            'BETWEEN' => $value >= $this->value[0] && $value <= $this->value[1],
            default => throw new TSDBException("Operator $this->operator not supported"),
        };
    }
}
