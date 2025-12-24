<?php

namespace TimeseriesPhp\Core\Enum;

enum VariableType: string
{
    case STRING = 'string';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case BOOLEAN = 'boolean';
}
