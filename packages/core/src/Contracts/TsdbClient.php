<?php

namespace TimeseriesPhp\Core\Contracts;

use TimeseriesPhp\Core\Timeseries\TimeSeriesResult;

interface TsdbClient
{
    public function query(CompiledQuery $query): TimeSeriesResult;
}
