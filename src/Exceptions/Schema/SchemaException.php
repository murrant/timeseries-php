<?php

namespace TimeSeriesPhp\Exceptions\Schema;

use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Base exception for schema-related errors
 */
class SchemaException extends TSDBException
{
    /**
     * @param string $message The exception message
     * @param int $code The exception code
     * @param \Throwable|null $previous The previous throwable
     */
    public function __construct(string $message = 'Schema error', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}