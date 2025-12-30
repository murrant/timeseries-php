<?php

use TimeseriesPhp\Core\Query\AST\TimeRange;

test('throws exception when no parameters provided', function (): void {
    new TimeRange;
})->throws(InvalidArgumentException::class, 'Provide at least one parameter.');

test('creates range from start and end', function (): void {
    $start = new DateTimeImmutable('2024-01-01 00:00:00');
    $end = new DateTimeImmutable('2024-01-31 23:59:59');

    $range = new TimeRange($start, $end);

    expect($range->start)->toEqual($start)
        ->and($range->end)->toEqual($end)
        ->and($range->startWasProvided)->toBeTrue()
        ->and($range->endWasProvided)->toBeTrue();
});

test('creates range from start and duration', function (): void {
    $start = new DateTimeImmutable('2024-01-01 00:00:00');
    $duration = new DateInterval('P7D');

    $range = new TimeRange($start, duration: $duration);

    expect($range->start)->toEqual($start)
        ->and($range->end)->toEqual($start->add($duration))
        ->and($range->startWasProvided)->toBeTrue()
        ->and($range->endWasProvided)->toBeFalse();
});

test('creates range from end and duration', function (): void {
    $end = new DateTimeImmutable('2024-01-31 23:59:59');
    $duration = new DateInterval('P7D');

    $range = new TimeRange(end: $end, duration: $duration);

    expect($range->start)->toEqual($end->sub($duration))
        ->and($range->end)->toEqual($end)
        ->and($range->startWasProvided)->toBeFalse()
        ->and($range->endWasProvided)->toBeTrue();
});

test('creates range from start only (end is now)', function (): void {
    $start = new DateTimeImmutable('2024-01-01 00:00:00');
    $beforeCreation = new DateTimeImmutable;

    $range = new TimeRange($start);

    $afterCreation = new DateTimeImmutable;

    expect($range->start)->toEqual($start)
        ->and($range->end->getTimestamp())->toBeGreaterThanOrEqual($beforeCreation->getTimestamp())
        ->and($range->end->getTimestamp())->toBeLessThanOrEqual($afterCreation->getTimestamp())
        ->and($range->startWasProvided)->toBeTrue()
        ->and($range->endWasProvided)->toBeFalse();
});

test('creates range from end only (start is now)', function (): void {
    $end = new DateTimeImmutable('2025-12-31 23:59:59');
    $beforeCreation = new DateTimeImmutable;

    $range = new TimeRange(end: $end);

    $afterCreation = new DateTimeImmutable;

    expect($range->end)->toEqual($end)
        ->and($range->start->getTimestamp())->toBeGreaterThanOrEqual($beforeCreation->getTimestamp())
        ->and($range->start->getTimestamp())->toBeLessThanOrEqual($afterCreation->getTimestamp())
        ->and($range->startWasProvided)->toBeFalse()
        ->and($range->endWasProvided)->toBeTrue();
});

test('creates range from duration only (last X period)', function (): void {
    $duration = new DateInterval('P1D');
    $beforeCreation = new DateTimeImmutable;

    $range = new TimeRange(duration: $duration);

    $afterCreation = new DateTimeImmutable;
    $expectedStart = $afterCreation->sub($duration);

    expect($range->end->getTimestamp())->toBeGreaterThanOrEqual($beforeCreation->getTimestamp())
        ->and($range->end->getTimestamp())->toBeLessThanOrEqual($afterCreation->getTimestamp())
        ->and($range->start->getTimestamp())->toBeGreaterThanOrEqual($expectedStart->getTimestamp() - 1)
        ->and($range->startWasProvided)->toBeFalse()
        ->and($range->endWasProvided)->toBeFalse();
});

test('validates start is not after end', function (): void {
    $start = new DateTimeImmutable('2024-01-31 23:59:59');
    $end = new DateTimeImmutable('2024-01-01 00:00:00');

    new TimeRange($start, $end);
})->throws(InvalidArgumentException::class, 'Start cannot be after end.');

test('validates duration matches start and end when all provided', function (): void {
    $start = new DateTimeImmutable('2024-01-01 00:00:00');
    $end = new DateTimeImmutable('2024-01-31 23:59:59');
    $wrongDuration = new DateInterval('P1D');

    new TimeRange($start, $end, $wrongDuration);
})->throws(InvalidArgumentException::class, 'Duration does not match start and end.');

test('accepts matching duration with start and end', function (): void {
    $start = new DateTimeImmutable('2024-01-01 00:00:00');
    $duration = new DateInterval('P7D');
    $end = $start->add($duration);

    $range = new TimeRange($start, $end, $duration);

    expect($range->start)->toEqual($start)
        ->and($range->end)->toEqual($end);
});

test('handles DateTime interface conversion', function (): void {
    $start = new DateTime('2024-01-01 00:00:00');
    $end = new DateTime('2024-01-31 23:59:59');

    $range = new TimeRange($start, $end);

    expect($range->start)->toBeInstanceOf(DateTimeImmutable::class)
        ->and($range->end)->toBeInstanceOf(DateTimeImmutable::class);
});

test('duration only creates relative range ending now', function (): void {
    $duration = new DateInterval('PT1H'); // 1 hour

    $range = new TimeRange(duration: $duration);

    $now = new DateTimeImmutable;
    $diff = $now->getTimestamp() - $range->end->getTimestamp();

    expect($diff)->toBeLessThanOrEqual(1) // Within 1 second
        ->and($range->startWasProvided)->toBeFalse()
        ->and($range->endWasProvided)->toBeFalse();
});

test('immutability of start and end', function (): void {
    $start = new DateTime('2024-01-01');
    $end = new DateTime('2024-01-31');

    $range = new TimeRange($start, $end);

    // This should not affect the range
    $start = $start->modify('+1 year');
    $end = $end->modify('+1 year');

    expect($range->start->format('Y-m-d'))->toBe('2024-01-01')
        ->and($range->end->format('Y-m-d'))->toBe('2024-01-31');
});
