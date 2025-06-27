<?php

namespace TimeSeriesPhp\Core\Schema;

use TimeSeriesPhp\Exceptions\Schema\SchemaException;

/**
 * Class representing a measurement schema
 */
class MeasurementSchema
{
    /**
     * @var array<string, FieldDefinition> Field definitions
     */
    private array $fields = [];

    /**
     * @var array<string, TagDefinition> Tag definitions
     */
    private array $tags = [];

    /**
     * @var array<string, mixed> Additional options for the measurement
     */
    private array $options = [];

    /**
     * @param string $name The name of the measurement
     * @param array<string, FieldDefinition>|null $fields Field definitions
     * @param array<string, TagDefinition>|null $tags Tag definitions
     * @param array<string, mixed>|null $options Additional options
     */
    public function __construct(
        private readonly string $name,
        ?array $fields = null,
        ?array $tags = null,
        ?array $options = null
    ) {
        if ($fields !== null) {
            $this->fields = $fields;
        }

        if ($tags !== null) {
            $this->tags = $tags;
        }

        if ($options !== null) {
            $this->options = $options;
        }
    }

    /**
     * Get the measurement name
     *
     * @return string The measurement name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Add a field to the schema
     *
     * @param string $name The field name
     * @param FieldDefinition $definition The field definition
     * @return self
     */
    public function addField(string $name, FieldDefinition $definition): self
    {
        $this->fields[$name] = $definition;
        return $this;
    }

    /**
     * Get all field definitions
     *
     * @return array<string, FieldDefinition> The field definitions
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * Get a specific field definition
     *
     * @param string $name The field name
     * @return FieldDefinition The field definition
     * @throws SchemaException If the field does not exist
     */
    public function getField(string $name): FieldDefinition
    {
        if (!isset($this->fields[$name])) {
            throw new SchemaException("Field '{$name}' does not exist in measurement '{$this->name}'");
        }

        return $this->fields[$name];
    }

    /**
     * Check if a field exists
     *
     * @param string $name The field name
     * @return bool True if the field exists
     */
    public function hasField(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /**
     * Add a tag to the schema
     *
     * @param string $name The tag name
     * @param TagDefinition $definition The tag definition
     * @return self
     */
    public function addTag(string $name, TagDefinition $definition): self
    {
        $this->tags[$name] = $definition;
        return $this;
    }

    /**
     * Get all tag definitions
     *
     * @return array<string, TagDefinition> The tag definitions
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * Get a specific tag definition
     *
     * @param string $name The tag name
     * @return TagDefinition The tag definition
     * @throws SchemaException If the tag does not exist
     */
    public function getTag(string $name): TagDefinition
    {
        if (!isset($this->tags[$name])) {
            throw new SchemaException("Tag '{$name}' does not exist in measurement '{$this->name}'");
        }

        return $this->tags[$name];
    }

    /**
     * Check if a tag exists
     *
     * @param string $name The tag name
     * @return bool True if the tag exists
     */
    public function hasTag(string $name): bool
    {
        return isset($this->tags[$name]);
    }

    /**
     * Set an option for the measurement
     *
     * @param string $name The option name
     * @param mixed $value The option value
     * @return self
     */
    public function setOption(string $name, mixed $value): self
    {
        $this->options[$name] = $value;
        return $this;
    }

    /**
     * Get all options
     *
     * @return array<string, mixed> The options
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get a specific option
     *
     * @param string $name The option name
     * @param mixed $default Default value if option doesn't exist
     * @return mixed The option value
     */
    public function getOption(string $name, mixed $default = null): mixed
    {
        return $this->options[$name] ?? $default;
    }

    /**
     * Check if an option exists
     *
     * @param string $name The option name
     * @return bool True if the option exists
     */
    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Convert the schema to an array
     *
     * @return array<string, mixed> The schema as an array
     */
    public function toArray(): array
    {
        $fieldsArray = [];
        foreach ($this->fields as $name => $definition) {
            $fieldsArray[$name] = $definition->toArray();
        }

        $tagsArray = [];
        foreach ($this->tags as $name => $definition) {
            $tagsArray[$name] = $definition->toArray();
        }

        return [
            'name' => $this->name,
            'fields' => $fieldsArray,
            'tags' => $tagsArray,
            'options' => $this->options,
        ];
    }

    /**
     * Create a schema from an array
     *
     * @param array<string, mixed> $data The schema data
     * @return self The created schema
     * @throws SchemaException If the schema data is invalid
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['name']) || !is_string($data['name'])) {
            throw new SchemaException('Schema must have a name');
        }

        $schema = new self($data['name']);

        if (isset($data['fields']) && is_array($data['fields'])) {
            foreach ($data['fields'] as $name => $fieldData) {
                $schema->addField($name, FieldDefinition::fromArray($fieldData));
            }
        }

        if (isset($data['tags']) && is_array($data['tags'])) {
            foreach ($data['tags'] as $name => $tagData) {
                $schema->addTag($name, TagDefinition::fromArray($tagData));
            }
        }

        if (isset($data['options']) && is_array($data['options'])) {
            foreach ($data['options'] as $name => $value) {
                $schema->setOption($name, $value);
            }
        }

        return $schema;
    }
}