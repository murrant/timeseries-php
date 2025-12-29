<?php

namespace TimeseriesPhp\Core\Enum;

enum Aggregation: string
{
    case AVG = 'avg';
    case LAST = 'last';
    case MAX = 'max';
    case MEDIAN = 'median';
    case MIN = 'min';
    case PERCENTILE = 'percentile';
    case RATE = 'rate';
    case SUM = 'sum';
}
