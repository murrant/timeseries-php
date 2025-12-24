<?php

namespace TimeseriesPhp\Core\Enum;

enum MatchType: string
{
    case EXACT = 'exact';
    case REGEX = 'regex';
    case NOT = 'not';
}
