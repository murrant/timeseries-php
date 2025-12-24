<?php

namespace TimeseriesPhp\Core\Enum;

enum GraphType: string
{
    case LINE = 'line';
    case AREA = 'area';
    case BAR = 'bar';
    case PIE = 'pie';
    case DOUGHNUT = 'doughnut';
    case GAUGE = 'gauge';
    case TABLE = 'table';
    case MAP = 'map';
    case GRID = 'grid';
    case SCATTER = 'scatter';
}
