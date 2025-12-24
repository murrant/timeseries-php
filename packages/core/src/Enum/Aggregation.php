<?php

namespace TimeseriesPhp\Core\Enum;

enum Aggregation: string
{
    case AVG = 'avg';
    case SUM = 'sum';
    case MIN = 'min';
    case MAX = 'max';
    case RATE = 'rate';
    case NONE = 'none';
}
