<?php

namespace TimeseriesPhp\Core\Enum;

enum Operator: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case Regex = '=~';
    case NotRegex = '!~';
    case In = 'IN';
    case NotIn = 'NOT_IN';
}
