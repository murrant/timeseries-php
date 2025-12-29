<?php

use App\Console\Commands\CollectPortStats;
use Illuminate\Support\Facades\Schedule;

Schedule::command(CollectPortStats::class)
    ->everyMinute();
