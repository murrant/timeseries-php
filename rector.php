<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/packages/core/src',
        __DIR__.'/packages/core/tests',
        __DIR__.'/packages/influxdb2-driver/src',
        __DIR__.'/packages/influxdb2-driver/tests',
        __DIR__.'/packages/laravel-bridge/src',
        __DIR__.'/packages/laravel-bridge/tests',
        __DIR__.'/packages/rrd-driver/src',
        __DIR__.'/packages/rrd-driver/tests',
        __DIR__.'/packages/web/app',
        __DIR__.'/packages/web/config',
        __DIR__.'/packages/web/database',
        __DIR__.'/packages/web/tests',
        __DIR__.'/tests',
    ])
    ->withPhpSets()
    ->withTypeCoverageLevel(0)
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0);
