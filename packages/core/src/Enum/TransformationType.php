<?php

namespace TimeseriesPhp\Core\Enum;

enum TransformationType: string {
    case Rate = 'rate';
    case Delta = 'delta';
    case Math = 'math';
    case GroupBy = 'groupby';
}
