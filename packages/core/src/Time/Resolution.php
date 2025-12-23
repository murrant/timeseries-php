<?php

namespace TimeseriesPhp\Core\Time;

enum Resolution
{
    case S; // second
    case MS; // millisecond
    case US; // microsecond
    case NS; // nanosecond
}
