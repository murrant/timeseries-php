<?php

namespace TimeseriesPhp\Core\Enum;

enum MetricType: string
{
    case COUNTER = 'counter';
    case GAUGE = 'gauge';
    case HISTOGRAM = 'histogram';
    case SUMMARY = 'summary';
}
