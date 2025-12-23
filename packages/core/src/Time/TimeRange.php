<?php

namespace TimeseriesPhp\Core\Time;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

final readonly class TimeRange
{
    public function __construct(
        public ?DateTimeInterface $start = null,
        public ?DateTimeInterface $end = null,
        public ?DateInterval $duration = null,
    ) {
    }

    public function getStart(): DateTimeImmutable
    {
        if ($this->start !== null) {
            return DateTimeImmutable::createFromInterface($this->start);
        }

        return DateTimeImmutable::createFromInterface($this->end)->sub($this->duration);
    }

    public function getEnd(): DateTimeImmutable
    {
        if ($this->end !== null) {
            return DateTimeImmutable::createFromInterface($this->end);
        }

        if ($this->duration !== null) {
            return DateTimeImmutable::createFromInterface($this->start)->add($this->duration);
        }

        return new DateTimeImmutable;
    }
}
