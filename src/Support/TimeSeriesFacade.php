<?php

namespace TimeSeriesPhp\Support;

use Illuminate\Support\Facades\Facade;

class TimeSeriesFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'time-series';
    }
}
