<?php

namespace TimeseriesPhp\Driver\Null;

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Core\Contracts\GraphCompiler;
use TimeseriesPhp\Core\Time\TimeRange;
use TimeseriesPhp\Core\Timeseries\Resolution;

final class NullCompiler implements GraphCompiler
{
    public function compile(BoundGraph $graph, TimeRange $range, ?Resolution $resolution = null): CompiledQuery
    {
        return new class implements CompiledQuery {};
    }
}
