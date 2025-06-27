<?php

namespace TimeSeriesPhp\Core\Schema;

/**
 * Class representing the result of schema validation
 */
class SchemaValidationResult
{
    /**
     * @var array<string, string> Validation errors by field/tag name
     */
    private array $errors = [];

    /**
     * @param bool $valid Whether the validation was successful
     * @param array<string, string>|null $errors Validation errors by field/tag name
     */
    public function __construct(
        private bool $valid = true,
        ?array $errors = null
    ) {
        if ($errors !== null) {
            $this->errors = $errors;
            $this->valid = empty($errors);
        }
    }

    /**
     * Check if the validation was successful
     *
     * @return bool True if the validation was successful
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Get all validation errors
     *
     * @return array<string, string> Validation errors by field/tag name
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Add a validation error
     *
     * @param string $field The field or tag name
     * @param string $message The error message
     * @return self
     */
    public function addError(string $field, string $message): self
    {
        $this->errors[$field] = $message;
        $this->valid = false;
        return $this;
    }

    /**
     * Check if a field or tag has an error
     *
     * @param string $field The field or tag name
     * @return bool True if the field or tag has an error
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /**
     * Get the error message for a field or tag
     *
     * @param string $field The field or tag name
     * @return string|null The error message, or null if no error
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    /**
     * Merge another validation result into this one
     *
     * @param SchemaValidationResult $other The other validation result
     * @return self
     */
    public function merge(SchemaValidationResult $other): self
    {
        $this->valid = $this->valid && $other->isValid();
        $this->errors = array_merge($this->errors, $other->getErrors());
        return $this;
    }

    /**
     * Create a valid validation result
     *
     * @return self
     */
    public static function valid(): self
    {
        return new self(true);
    }

    /**
     * Create an invalid validation result with the specified errors
     *
     * @param array<string, string> $errors Validation errors by field/tag name
     * @return self
     */
    public static function invalid(array $errors): self
    {
        return new self(false, $errors);
    }
}