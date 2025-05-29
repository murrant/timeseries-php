<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Core;

/**
 * Data Transfer Object for query conditions
 */
readonly class QueryCondition
{
    /**
     * @param  string  $field  The field name to apply the condition to
     * @param  string  $operator  The operator for the condition (=, >, <, etc.)
     * @param  null|bool|float|int|string|array<?scalar>  $value  The value to compare against
     * @param  'AND'|'OR'  $type  The type of condition (AND or OR)
     */
    public function __construct(
        private string $field,
        private string $operator,
        private null|bool|float|int|string|array $value,
        private string $type = 'AND',
    ) {}

    /**
     * Get the field name
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * Get the operator
     */
    public function getOperator(): string
    {
        return $this->operator;
    }

    /**
     * Get the value
     *
     * @return null|bool|float|int|string|array<?scalar>
     */
    public function getValue(): null|bool|float|int|string|array
    {
        return $this->value;
    }

    public function getScalarValue(): null|bool|float|int|string
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

    /**
     * Get the condition type (AND/OR)
     *
     * @return 'AND'|'OR'
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Convert to array format for backward compatibility
     *
     * @return array{field: string, operator: string, value: scalar|null|array<?scalar>, type: 'AND'|'OR'}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator,
            'value' => $this->value,
            'type' => $this->type,
        ];
    }

    /**
     * Create a new condition with AND type
     *
     * @param  string  $field  The field name
     * @param  string  $operator  The operator
     * @param  null|bool|float|int|string|array<?scalar>  $value  The value
     */
    public static function where(string $field, string $operator, null|bool|float|int|string|array $value): self
    {
        return new self($field, $operator, $value, 'AND');
    }

    /**
     * Create a new condition with OR type
     *
     * @param  string  $field  The field name
     * @param  string  $operator  The operator
     * @param  null|bool|float|int|string|array<?scalar>  $value  The value
     */
    public static function orWhere(string $field, string $operator, null|bool|float|int|string|array $value): self
    {
        return new self($field, $operator, $value, 'OR');
    }

    /**
     * Create a new IN condition
     *
     * @param  string  $field  The field name
     * @param  array<int, ?scalar>  $values  The values
     */
    public static function whereIn(string $field, array $values): self
    {
        return new self($field, 'IN', $values, 'AND');
    }

    /**
     * Create a new NOT IN condition
     *
     * @param  string  $field  The field name
     * @param  array<int, ?scalar>  $values  The values
     */
    public static function whereNotIn(string $field, array $values): self
    {
        return new self($field, 'NOT IN', $values, 'AND');
    }

    /**
     * Create a new BETWEEN condition
     *
     * @param  string  $field  The field name
     * @param  int|float  $min  The minimum value
     * @param  int|float  $max  The maximum value
     */
    public static function whereBetween(string $field, int|float $min, int|float $max): self
    {
        return new self($field, 'BETWEEN', [$min, $max], 'AND');
    }

    /**
     * Create a new REGEX condition
     *
     * @param  string  $field  The field name
     * @param  string  $pattern  The regex pattern
     */
    public static function whereRegex(string $field, string $pattern): self
    {
        return new self($field, 'REGEX', $pattern, 'AND');
    }
}
