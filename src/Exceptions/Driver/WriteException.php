<?php

namespace TimeSeriesPhp\Exceptions\Driver;

use TimeSeriesPhp\Exceptions\TSDBException;

class WriteException extends TSDBException
{
    protected bool $retryable = true;

    /**
     * Check if the exception is retryable
     */
    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    /**
     * Set whether the exception is retryable
     */
    public function setRetryable(bool $retryable): self
    {
        $this->retryable = $retryable;

        return $this;
    }
}
