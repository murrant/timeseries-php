<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Exceptions\Container;

use Psr\Container\NotFoundExceptionInterface;
use TimeSeriesPhp\Exceptions\TSDBException;

/**
 * Exception thrown when a service is not found in the container
 */
class ServiceNotFoundException extends TSDBException implements NotFoundExceptionInterface
{
    /**
     * @param  string  $id  The service id that was not found
     * @param  string  $message  The exception message
     * @param  int  $code  The exception code
     * @param  \Throwable|null  $previous  The previous exception
     */
    public function __construct(
        private readonly string $id,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $message = $message ?: sprintf('Service "%s" not found in container', $id);
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the service id that was not found
     */
    public function getServiceId(): string
    {
        return $this->id;
    }
}
