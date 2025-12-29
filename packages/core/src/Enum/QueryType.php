<?php

namespace TimeseriesPhp\Core\Enum;

enum QueryType {
    case Data;
    case Labels;
    case Metrics;
}
