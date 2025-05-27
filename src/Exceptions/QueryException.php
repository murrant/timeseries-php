<?php

namespace TimeSeriesPhp\Exceptions;

use Throwable;
use TimeSeriesPhp\Core\RawQueryContract;

class QueryException extends TSDBException
{
    public function __construct(
        public readonly RawQueryContract $rawQuery,
        string $message = "",
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRawQuery(): string
    {
        return $this->rawQuery->getRawQuery();
    }
}
