<?php

namespace TimeseriesPhp\Core\Enum;

enum Aggregation: string
{
    case Average = 'avg';
    case Last = 'last';
    case Maximum = 'max';
    case Median = 'median';
    case Minimum = 'min';
    case Percentile = 'percentile';
    case Rate = 'rate';
    case Sum = 'sum';
}
