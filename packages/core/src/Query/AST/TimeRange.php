<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core\Query\AST;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

final readonly class TimeRange
{
    public DateTimeImmutable $start;

    public DateTimeImmutable $end;

    public bool $startWasProvided;

    public bool $endWasProvided;

    public function __construct(
        ?DateTimeInterface $start = null,
        ?DateTimeInterface $end = null,
        ?DateInterval $duration = null
    ) {
        if (! $start && ! $end && ! $duration) {
            throw new InvalidArgumentException('Provide at least one parameter.');
        }

        $this->startWasProvided = $start !== null;
        $this->endWasProvided = $end !== null;

        $start = $start ? DateTimeImmutable::createFromInterface($start) : null;
        $end = $end ? DateTimeImmutable::createFromInterface($end) : null;

        if ($start && $end) {
            if ($duration && abs($start->add($duration)->getTimestamp() - $end->getTimestamp()) > 1) {
                throw new InvalidArgumentException('Duration does not match start and end.');
            }
        } elseif ($start && $duration) {
            $end = $start->add($duration);
        } elseif ($end && $duration) {
            $start = $end->sub($duration);
        } elseif ($start) {
            $end = new DateTimeImmutable;
        } elseif ($end) {
            $start = new DateTimeImmutable;
        } else {
            if ($duration === null) {
                throw new InvalidArgumentException('Provide duration. (how tf did you get here? this is to appease phpstan)');
            }

            $end = new DateTimeImmutable;
            $start = $end->sub($duration);
        }

        if ($start > $end) {
            throw new InvalidArgumentException('Start cannot be after end.');
        }

        $this->start = $start;
        $this->end = $end;
    }

    public static function lastMinutes(int $minutes): self
    {
        return new self(
            duration: new DateInterval("PT{$minutes}M")
        );
    }

    public static function lastHours(int $hours): self
    {
        return new self(
            duration: new DateInterval("PT{$hours}H")
        );
    }

    public static function lastDays(int $days): self
    {
        return new self(
            duration: new DateInterval("P{$days}D")
        );
    }

    public function equals(TimeRange $range): bool
    {
        return $this->start->getTimestamp() === $range->start->getTimestamp() && $this->end->getTimestamp() === $range->end->getTimestamp();
    }
}
