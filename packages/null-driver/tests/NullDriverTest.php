<?php

declare(strict_types=1);

use TimeseriesPhp\Core\Contracts\CompiledQuery;
use TimeseriesPhp\Driver\Null\NullCapabilities;
use TimeseriesPhp\Driver\Null\NullClient;
use TimeseriesPhp\Driver\Null\NullDriver;

test('null driver returns null capabilities', function () {
    $driver = new NullDriver;
    $capabilities = $driver->getCapabilities();

    expect($capabilities)->toBeInstanceOf(NullCapabilities::class)
        ->and($capabilities->supportsRate())->toBeFalse()
        ->and($capabilities->supportsHistogram())->toBeFalse()
        ->and($capabilities->supportsLabelJoin())->toBeFalse();
});

test('null tsdb client returns empty result', function () {
    $client = new NullClient;
    $query = new class implements CompiledQuery {};

    $result = $client->query($query);

    expect($result->hasData())->toBeFalse()
        ->and($result->series)->toBeEmpty();
});
