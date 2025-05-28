<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Utils\File;

readonly class TagCondition
{
    /**
     * @param  array<?scalar>|scalar|null  $value
     */
    public function __construct(
        public string $tag,
        public string $operator,
        public float|int|bool|string|null|array $value,
        public string $condition = 'AND',
    ) {}

    /**
     * @throws TSDBException
     */
    public function matches(string $value): bool
    {
        return match ($this->operator) {
            '=' => File::sanitizeTag($this->getStringValue()) === File::sanitizeTag($value),
            'IN' => in_array(File::sanitizeTag($value), array_map(fn ($v) => File::sanitizeTag((string) $v), $this->getValues())),
            'REGEX' => (bool) preg_match($this->getStringValue(), File::sanitizeTag($value)),
            'NOT IN' => ! in_array(File::sanitizeTag($value), array_map(fn ($v) => File::sanitizeTag((string) $v), $this->getValues())),
            'BETWEEN' => $value >= $this->getValues()[0] && $value <= $this->getValues()[1],
            default => throw new TSDBException("Operator $this->operator not supported"),
        };
    }

    public function getStringValue(): string
    {
        return (string) $this->getScalarValue();
    }

    public function getScalarValue(): float|int|bool|string|null
    {
        return is_array($this->value) ? $this->value[0] ?? null : $this->value;
    }

    /**
     * @return array<?scalar>
     */
    public function getValues(): array
    {
        return is_array($this->value) ? $this->value : [$this->value];
    }
}
