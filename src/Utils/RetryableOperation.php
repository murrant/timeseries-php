<?php

namespace TimeSeriesPhp\Utils;

/**
 * Utility class for retrying operations that might fail temporarily.
 */
class RetryableOperation
{
    /**
     * Execute a callable with retry logic
     *
     * @param  callable  $operation  The operation to execute
     * @param  int  $maxRetries  Maximum number of retries (default: 3)
     * @param  int  $delay  Delay between retries in milliseconds (default: 100)
     * @param  float  $backoffFactor  Factor to increase delay by after each retry (default: 2.0)
     * @param  array<class-string<\Throwable>>  $retryableExceptions  Exceptions that should trigger a retry
     * @return mixed The result of the operation
     *
     * @throws \Throwable If the operation fails after all retries
     */
    public static function execute(
        callable $operation,
        int $maxRetries = 3,
        int $delay = 100,
        float $backoffFactor = 2.0,
        array $retryableExceptions = [\Exception::class]
    ): mixed {
        $attempt = 0;
        $currentDelay = $delay;
        $lastException = null;

        while ($attempt <= $maxRetries) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                // Check if this exception should trigger a retry
                $shouldRetry = false;
                foreach ($retryableExceptions as $exceptionClass) {
                    if ($e instanceof $exceptionClass) {
                        $shouldRetry = true;
                        break;
                    }
                }

                // If this is not a retryable exception or we've reached max retries, throw it
                if (! $shouldRetry || $attempt >= $maxRetries) {
                    throw $e;
                }

                // Wait before retrying
                if ($currentDelay > 0) {
                    usleep($currentDelay * 1000); // Convert to microseconds
                }

                // Increase delay for next retry
                $currentDelay = (int) ($currentDelay * $backoffFactor);
                $attempt++;
            }
        }

        // This should never be reached, but just in case
        throw $lastException ?? new \RuntimeException('Operation failed after retries');
    }

    /**
     * Create a retryable version of a callable
     *
     * @param  callable  $operation  The operation to make retryable
     * @param  int  $maxRetries  Maximum number of retries (default: 3)
     * @param  int  $delay  Delay between retries in milliseconds (default: 100)
     * @param  float  $backoffFactor  Factor to increase delay by after each retry (default: 2.0)
     * @param  array<class-string<\Throwable>>  $retryableExceptions  Exceptions that should trigger a retry
     * @return callable A new callable that will retry the operation
     */
    public static function makeRetryable(
        callable $operation,
        int $maxRetries = 3,
        int $delay = 100,
        float $backoffFactor = 2.0,
        array $retryableExceptions = [\Exception::class]
    ): callable {
        return function (...$args) use ($operation, $maxRetries, $delay, $backoffFactor, $retryableExceptions) {
            return self::execute(
                fn () => $operation(...$args),
                $maxRetries,
                $delay,
                $backoffFactor,
                $retryableExceptions
            );
        };
    }
}
