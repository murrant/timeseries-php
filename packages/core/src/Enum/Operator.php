<?php

namespace TimeseriesPhp\Core\Enum;

enum Operator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case RegexMatch = '=~';
    case RegexNotMatch = '!~';
    case In = 'IN';
}
