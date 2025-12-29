<?php

namespace TimeseriesPhp\Core\Graph;

use TimeseriesPhp\Core\Enum\MatchType;
use TimeseriesPhp\Core\Enum\VariableType;

final readonly class GraphVariable
{
    public function __construct(
        public string $name,
        public VariableType $type,
        public bool $required = false,
        public mixed $default = null,
        /** @var MatchType[] */
        public array $allowedOperators = [MatchType::EQUALS],
    ) {}

    public static function fromArray(array $variable): self
    {
        return new self(
            name: $variable['name'],
            type: VariableType::from($variable['type']),
            required: $variable['required'] ?? false,
            default: $variable['default'] ?? null,
            allowedOperators: array_map(MatchType::from(...), $variable['allowedOperators'] ?? ['=']),
        );
    }

    /**
     * @return array{name: string, type: string, required: bool, default: mixed|null, allowedOperators: mixed}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'type' => $this->type->name,
            'required' => $this->required,
            'default' => $this->default,
            'allowedOperators' => array_map(fn (MatchType $op) => $op->name, $this->allowedOperators),
        ];
    }
}
