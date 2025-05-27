<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Utils\File;

readonly class TagCondition
{
    public function __construct(
        public string $tag,
        public string $operator,
        public mixed $value,
        public string $condition = 'AND',
    ) {}

    public function matches(string $value): bool
    {
        return match ($this->operator) {
            '=' => File::sanitizeTag($this->value) === File::sanitizeTag($value),
            'IN' => in_array(File::sanitizeTag($value), array_map(fn ($v) => File::sanitizeTag($v), $this->value)),
            'REGEX' => preg_match($this->value, File::sanitizeTag($value)),
            'NOT IN' => ! in_array(File::sanitizeTag($value), array_map(fn ($v) => File::sanitizeTag($v), $this->value)),
            'BETWEEN' => $value >= $this->value[0] && $value <= $this->value[1],
            default => throw new TSDBException("Operator $this->operator not supported"),
        };
    }
}
