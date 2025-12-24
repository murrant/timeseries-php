<?php

namespace TimeseriesPhp\Core\Enum;

enum TimestampResolution
{
    case S; // second
    case MS; // millisecond
    case US; // microsecond
    case NS; // nanosecond
}
