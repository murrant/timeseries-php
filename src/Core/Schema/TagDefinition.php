<?php

namespace TimeSeriesPhp\Core\Schema;

use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Class representing a tag definition in a measurement schema
 */
class TagDefinition
{
    /**
     * @var array<string, mixed> Validation rules for the tag
     */
    private array $validationRules = [];

    /**
     * @param  bool  $required  Whether the tag is required
     * @param  array<string>|null  $allowedValues  List of allowed values (enum)
     * @param  array<string, mixed>|null  $validationRules  Validation rules
     */
    public function __construct(
        private readonly bool $required = false,
        private ?array $allowedValues = null,
        ?array $validationRules = null
    ) {
        if ($validationRules !== null) {
            $this->validationRules = $validationRules;
        }
    }

    /**
     * Check if the tag is required
     *
     * @return bool True if the tag is required
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * Get the allowed values for the tag
     *
     * @return array<string>|null The allowed values
     */
    public function getAllowedValues(): ?array
    {
        return $this->allowedValues;
    }

    /**
     * Set the allowed values for the tag
     *
     * @param  array<string>|null  $allowedValues  The allowed values
     */
    public function setAllowedValues(?array $allowedValues): self
    {
        $this->allowedValues = $allowedValues;

        return $this;
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
     * Validate a value against this tag definition
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

        // Tags must be strings
        if (! is_string($value)) {
            return false;
        }

        // Check allowed values if defined
        if ($this->allowedValues !== null && ! in_array($value, $this->allowedValues, true)) {
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
     * Convert the tag definition to an array
     *
     * @return array<string, mixed> The tag definition as an array
     */
    public function toArray(): array
    {
        return [
            'required' => $this->required,
            'allowedValues' => $this->allowedValues,
            'validationRules' => $this->validationRules,
        ];
    }

    /**
     * Create a tag definition from an array
     *
     * @param  array<string, mixed>  $data  The tag definition data
     * @return self The created tag definition
     *
     * @throws SchemaException If the tag definition data is invalid
     */
    public static function fromArray(array $data): self
    {
        $required = $data['required'] ?? false;
        $allowedValues = $data['allowedValues'] ?? null;
        $validationRules = $data['validationRules'] ?? null;

        return new self($required, $allowedValues, $validationRules);
    }

    /**
     * Validate a value against a specific rule
     *
     * @param  string  $value  The value to validate
     * @param  string  $rule  The rule name
     * @param  mixed  $ruleValue  The rule value
     * @return bool True if the value is valid against the rule
     */
    private function validateValueAgainstRule(string $value, string $rule, mixed $ruleValue): bool
    {
        return match ($rule) {
            'min' => strlen($value) >= $ruleValue,
            'max' => strlen($value) <= $ruleValue,
            'pattern' => preg_match($ruleValue, $value) === 1,
            default => true,
        };
    }
}
