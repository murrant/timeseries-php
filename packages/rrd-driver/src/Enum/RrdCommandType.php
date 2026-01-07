<?php

namespace TimeseriesPhp\Driver\RRD\Enum;

enum RrdCommandType: string
{
    case Create = 'create';
    case List = 'list';
    case Update = 'update';
    case Info = 'info';
    case Xport = 'xport';
    case Dump = 'dump';
    case Fetch = 'fetch';
    case Graph = 'graph';
    case Last = 'last';
    case First = 'first';
    case Tune = 'tune';
    case Resize = 'resize';
    case Flushcached = 'flushcached';
}
