<?php

use TimeseriesPhp\Core\Time\TimeRange;

test('it can be instantiated with start and end', function () {
    $start = new DateTimeImmutable('2023-01-01 00:00:00');
    $end = new DateTimeImmutable('2023-01-01 01:00:00');
    $range = new TimeRange(start: $start, end: $end);

    expect($range->getStart())->toEqual($start)
        ->and($range->getEnd())->toEqual($end);
});

test('it can calculate start from end and duration', function () {
    $end = new DateTimeImmutable('2023-01-01 01:00:00');
    $duration = new DateInterval('PT1H');
    $range = new TimeRange(end: $end, duration: $duration);

    expect($range->getStart())->toEqual(new DateTimeImmutable('2023-01-01 00:00:00'))
        ->and($range->getEnd())->toEqual($end);
});

test('it can calculate end from start and duration', function () {
    $start = new DateTimeImmutable('2023-01-01 00:00:00');
    $duration = new DateInterval('PT1H');
    $range = new TimeRange(start: $start, duration: $duration);

    expect($range->getStart())->toEqual($start)
        ->and($range->getEnd())->toEqual(new DateTimeImmutable('2023-01-01 01:00:00'));
});

test('it returns current time if end and duration are null', function () {
    $start = new DateTimeImmutable('2023-01-01 00:00:00');
    $range = new TimeRange(start: $start);

    $now = new DateTimeImmutable();
    $end = $range->getEnd();

    // Allow a small difference in time
    expect($end->getTimestamp())->toBeGreaterThanOrEqual($now->getTimestamp());
    expect($end->getTimestamp())->toBeLessThanOrEqual($now->getTimestamp() + 1);
});

test('getStart returns start if provided even if end and duration are also provided', function () {
    $start = new DateTimeImmutable('2023-01-01 00:00:00');
    $end = new DateTimeImmutable('2023-01-01 02:00:00');
    $duration = new DateInterval('PT1H');
    $range = new TimeRange(start: $start, end: $end, duration: $duration);

    expect($range->getStart())->toEqual($start);
});

test('getEnd returns end if provided even if duration is also provided', function () {
    $start = new DateTimeImmutable('2023-01-01 00:00:00');
    $end = new DateTimeImmutable('2023-01-01 02:00:00');
    $duration = new DateInterval('PT1H');
    $range = new TimeRange(start: $start, end: $end, duration: $duration);

    expect($range->getEnd())->toEqual($end);
});
