<?php

namespace TimeseriesPhp\Core\Graph;

use TimeseriesPhp\Core\Enum\VariableType;

final readonly class GraphVariable
{
    public function __construct(
        public string $name,
        public VariableType $type,
        public bool $required = false,
        public mixed $default = null,
        public array $constraints = [],
        public mixed $value = null,
    ) {}

    public static function fromArray(array $variable): self
    {
        return new self(
            name: $variable['name'],
            type: VariableType::from($variable['type']),
            required: $variable['required'],
            default: $variable['default'],
            constraints: $variable['constraints'],
        );
    }

    public function withValue(mixed $value): self
    {
        return new GraphVariable(
            name: $this->name,
            type: $this->type,
            required: $this->required,
            default: $this->default,
            constraints: $this->constraints,
            value: $value,
        );
    }
}
