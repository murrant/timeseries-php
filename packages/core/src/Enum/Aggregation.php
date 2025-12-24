<?php

namespace TimeseriesPhp\Core\Enum;

enum Aggregation
{
    case AVG;
    case SUM;
    case MIN;
    case MAX;
    case RATE;
    case NONE;
}
