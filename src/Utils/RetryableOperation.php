<?php

namespace TimeSeriesPhp\Utils;

/**
 * Utility class for retrying operations that might fail temporarily.
 */
class RetryableOperation
{
    /** @var callable */
    protected $operation;

    protected int $maxRetries = 3;

    protected int $delay = 100;

    protected float $backoffFactor = 2.0;

    /** @var array<class-string<\Throwable>> */
    protected array $retryableExceptions = [\Exception::class];

    /**
     * Create a new RetryableOperation instance with the given operation
     *
     * This is the starting point for the fluent interface. After creating an instance,
     * you can configure it using the fluent methods (retries(), delay(), etc.) and
     * then execute it using run().
     *
     * @param  callable  $operation  The operation to execute
     * @return self A new RetryableOperation instance
     */
    public static function make(callable $operation): self
    {
        return new self($operation);
    }

    /**
     * Execute the operation with retry logic
     *
     * This is the final step in the fluent interface. It executes the operation with
     * the configured retry settings.
     *
     * @return mixed The result of the operation
     *
     * @throws \Throwable If the operation fails after all retries
     */
    public function run(): mixed
    {
        $lastException = null;
        $operation = $this->operation;
        $currentDelay = $this->delay;
        $attempt = 0;

        while ($attempt <= $this->maxRetries) {
            try {
                return $operation();
            } catch (\Throwable $e) {
                $lastException = $e;

                // Check if this exception should trigger a retry
                $shouldRetry = false;
                foreach ($this->retryableExceptions as $exceptionClass) {
                    if ($e instanceof $exceptionClass) {
                        $shouldRetry = true;
                        break;
                    }
                }

                // If this is not a retryable exception or we've reached max retries, throw it
                if (! $shouldRetry || $attempt >= $this->maxRetries) {
                    throw $e;
                }

                // Wait before retrying
                if ($currentDelay > 0) {
                    usleep($currentDelay * 1000); // Convert to microseconds
                }

                // Increase delay for next retry
                $currentDelay = (int) ($currentDelay * $this->backoffFactor);
                $attempt++;
            }
        }

        // This should never be reached, but just in case
        throw $lastException ?? new \RuntimeException('Operation failed after retries');
    }

    public function retries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;

        return $this;
    }

    public function delay(int $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    public function backoffFactor(float $backoffFactor): self
    {
        $this->backoffFactor = $backoffFactor;

        return $this;
    }

    /**
     * @param  array<class-string<\Throwable>>  $retryableExceptions
     */
    public function retryableExceptions(array $retryableExceptions): self
    {
        $this->retryableExceptions = $retryableExceptions;

        return $this;
    }

    public function __invoke(): mixed
    {
        return $this->run();
    }

    protected function __construct(
        callable $operation
    ) {
        $this->operation = $operation;
    }
}
