<?php

namespace TimeSeriesPhp\Core\Schema;

use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Class representing a field definition in a measurement schema
 */
class FieldDefinition
{
    /**
     * @var array<string, mixed> Validation rules for the field
     */
    private array $validationRules = [];

    /**
     * @param  string  $type  The field type (float, integer, string, boolean)
     * @param  bool  $required  Whether the field is required
     * @param  mixed|null  $defaultValue  Default value for the field
     * @param  array<string, mixed>|null  $validationRules  Validation rules
     */
    public function __construct(
        private readonly string $type,
        private readonly bool $required = false,
        private readonly mixed $defaultValue = null,
        ?array $validationRules = null
    ) {
        $this->validateType($type);

        if ($validationRules !== null) {
            $this->validationRules = $validationRules;
        }
    }

    /**
     * Get the field type
     *
     * @return string The field type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Check if the field is required
     *
     * @return bool True if the field is required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Get the default value for the field
     *
     * @return mixed The default value
     */
    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /**
     * Add a validation rule
     *
     * @param  string  $rule  The rule name
     * @param  mixed  $value  The rule value
     */
    public function addValidationRule(string $rule, mixed $value): self
    {
        $this->validationRules[$rule] = $value;

        return $this;
    }

    /**
     * Get all validation rules
     *
     * @return array<string, mixed> The validation rules
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * Get a specific validation rule
     *
     * @param  string  $rule  The rule name
     * @param  mixed  $default  Default value if rule doesn't exist
     * @return mixed The rule value
     */
    public function getValidationRule(string $rule, mixed $default = null): mixed
    {
        return $this->validationRules[$rule] ?? $default;
    }

    /**
     * Check if a validation rule exists
     *
     * @param  string  $rule  The rule name
     * @return bool True if the rule exists
     */
    public function hasValidationRule(string $rule): bool
    {
        return isset($this->validationRules[$rule]);
    }

    /**
     * Validate a value against this field definition
     *
     * @param  mixed  $value  The value to validate
     * @return bool True if the value is valid
     */
    public function validateValue(mixed $value): bool
    {
        // Check if value is required but not provided
        if ($this->required && $value === null) {
            return false;
        }

        // If value is null and not required, it's valid
        if ($value === null && ! $this->required) {
            return true;
        }

        // Validate type
        if (! $this->validateValueType($value)) {
            return false;
        }

        // Validate against rules
        foreach ($this->validationRules as $rule => $ruleValue) {
            if (! $this->validateValueAgainstRule($value, $rule, $ruleValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert the field definition to an array
     *
     * @return array<string, mixed> The field definition as an array
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'required' => $this->required,
            'defaultValue' => $this->defaultValue,
            'validationRules' => $this->validationRules,
        ];
    }

    /**
     * Create a field definition from an array
     *
     * @param  array<string, mixed>  $data  The field definition data
     * @return self The created field definition
     *
     * @throws SchemaException If the field definition data is invalid
     */
    public static function fromArray(array $data): self
    {
        if (! isset($data['type']) || ! is_string($data['type'])) {
            throw new SchemaException('Field definition must have a type');
        }

        $required = $data['required'] ?? false;
        $defaultValue = $data['defaultValue'] ?? null;
        $validationRules = $data['validationRules'] ?? null;

        return new self($data['type'], $required, $defaultValue, $validationRules);
    }

    /**
     * Validate the field type
     *
     * @param  string  $type  The type to validate
     *
     * @throws SchemaException If the type is invalid
     */
    private function validateType(string $type): void
    {
        $validTypes = ['float', 'integer', 'string', 'boolean'];
        if (! in_array($type, $validTypes)) {
            throw new SchemaException("Invalid field type: {$type}. Valid types are: ".implode(', ', $validTypes));
        }
    }

    /**
     * Validate a value's type against this field's type
     *
     * @param  mixed  $value  The value to validate
     * @return bool True if the value's type is valid
     */
    private function validateValueType(mixed $value): bool
    {
        return match ($this->type) {
            'float' => is_float($value) || is_int($value),
            'integer' => is_int($value),
            'string' => is_string($value),
            'boolean' => is_bool($value),
            default => false,
        };
    }

    /**
     * Validate a value against a specific rule
     *
     * @param  mixed  $value  The value to validate
     * @param  string  $rule  The rule name
     * @param  mixed  $ruleValue  The rule value
     * @return bool True if the value is valid against the rule
     */
    private function validateValueAgainstRule(mixed $value, string $rule, mixed $ruleValue): bool
    {
        return match ($rule) {
            'min' => match ($this->type) {
                'float', 'integer' => $value >= $ruleValue,
                'string' => strlen((string) $value) >= $ruleValue,
                default => true,
            },
            'max' => match ($this->type) {
                'float', 'integer' => $value <= $ruleValue,
                'string' => strlen((string) $value) <= $ruleValue,
                default => true,
            },
            'pattern' => $this->type === 'string' && preg_match($ruleValue, (string) $value) === 1,
            'enum' => is_array($ruleValue) && in_array($value, $ruleValue, true),
            default => true,
        };
    }
}
