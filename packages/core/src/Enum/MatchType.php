<?php

namespace TimeseriesPhp\Core\Enum;

enum MatchType: string
{
    case EQUALS = 'equals';
    case REGEX = 'regex';
    case NOT_EQUALS = 'not_equals';
}
