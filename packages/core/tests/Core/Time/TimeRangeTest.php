<?php

declare(strict_types=1);

use TimeseriesPhp\Core\Time\TimeRange;

test('it can be instantiated with start and end', function (DateTimeInterface $start, DateTimeInterface $end): void {
    $range = new TimeRange(start: $start, end: $end);

    expect($range->getStart())->toEqual(DateTimeImmutable::createFromInterface($start))
        ->and($range->getEnd())->toEqual(DateTimeImmutable::createFromInterface($end));
})->with([
    'immutable' => [new DateTimeImmutable('2023-01-01 00:00:00'), new DateTimeImmutable('2023-01-01 01:00:00')],
    'mutable' => [new DateTime('2023-01-01 00:00:00'), new DateTime('2023-01-01 01:00:00')],
    'mixed' => [new DateTimeImmutable('2023-01-01 00:00:00'), new DateTime('2023-01-01 01:00:00')],
]);

test('it can calculate start from end and duration', function (string $endStr, string $durationStr, string $expectedStartStr): void {
    $end = new DateTimeImmutable($endStr);
    $duration = new DateInterval($durationStr);
    $range = new TimeRange(end: $end, duration: $duration);

    expect($range->getStart())->toEqual(new DateTimeImmutable($expectedStartStr))
        ->and($range->getEnd())->toEqual($end);
})->with([
    ['2023-01-01 01:00:00', 'PT1H', '2023-01-01 00:00:00'],
    ['2023-01-02 00:00:00', 'P1D', '2023-01-01 00:00:00'],
    ['2023-02-01 00:00:00', 'P1M', '2023-01-01 00:00:00'],
]);

test('it can calculate end from start and duration', function (string $startStr, string $durationStr, string $expectedEndStr): void {
    $start = new DateTimeImmutable($startStr);
    $duration = new DateInterval($durationStr);
    $range = new TimeRange(start: $start, duration: $duration);

    expect($range->getStart())->toEqual($start)
        ->and($range->getEnd())->toEqual(new DateTimeImmutable($expectedEndStr));
})->with([
    ['2023-01-01 00:00:00', 'PT1H', '2023-01-01 01:00:00'],
    ['2023-01-01 00:00:00', 'P1D', '2023-01-02 00:00:00'],
    ['2023-01-01 00:00:00', 'P1M', '2023-02-01 00:00:00'],
]);

test('it returns current time if end and duration are null', function (): void {
    $start = new DateTimeImmutable('2023-01-01 00:00:00');
    $range = new TimeRange(start: $start);

    $now = new DateTimeImmutable;
    $end = $range->getEnd();

    // Allow a small difference in time
    expect($end->getTimestamp())->toBeGreaterThanOrEqual($now->getTimestamp());
    expect($end->getTimestamp())->toBeLessThanOrEqual($now->getTimestamp() + 1);
});

test('getStart returns start if provided even if end and duration are also provided', function (): void {
    $start = new DateTimeImmutable('2023-01-01 00:00:00');
    $end = new DateTimeImmutable('2023-01-01 02:00:00');
    $duration = new DateInterval('PT1H');
    $range = new TimeRange(start: $start, end: $end, duration: $duration);

    expect($range->getStart())->toEqual($start);
});

test('getEnd returns end if provided even if duration is also provided', function (): void {
    $start = new DateTimeImmutable('2023-01-01 00:00:00');
    $end = new DateTimeImmutable('2023-01-01 02:00:00');
    $duration = new DateInterval('PT1H');
    $range = new TimeRange(start: $start, end: $end, duration: $duration);

    expect($range->getEnd())->toEqual($end);
});

test('it handles timezones correctly', function (): void {
    $tz = new DateTimeZone('America/New_York');
    $start = new DateTimeImmutable('2023-01-01 00:00:00', $tz);
    $duration = new DateInterval('PT1H');
    $range = new TimeRange(start: $start, duration: $duration);

    expect($range->getStart()->getTimezone()->getName())->toBe('America/New_York')
        ->and($range->getEnd()->getTimezone()->getName())->toBe('America/New_York')
        ->and($range->getEnd())->toEqual(new DateTimeImmutable('2023-01-01 01:00:00', $tz));
});

test('getStart throws TypeError if start and end are null', function (): void {
    $range = new TimeRange(duration: new DateInterval('PT1H'));
    $range->getStart();
})->throws(TypeError::class);

test('it returns new instances of DateTimeImmutable', function (): void {
    $start = new DateTimeImmutable('2023-01-01 00:00:00');
    $range = new TimeRange(start: $start);

    $start1 = $range->getStart();
    $start2 = $range->getStart();

    expect($start1)->not->toBe($start); // createFromInterface returns new instance
    expect($start1)->not->toBe($start2);
});
