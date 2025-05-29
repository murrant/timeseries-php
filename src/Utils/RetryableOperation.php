<?php

namespace TimeSeriesPhp\Utils;

use Psr\SimpleCache\CacheInterface;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Support\Cache\CacheFactory;

/**
 * Utility class for retrying operations that might fail temporarily.
 * Can persist failed operations to cache for later replay.
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

    protected ?CacheInterface $cache = null;

    protected bool $persistOnFailure = false;

    protected string $operationId = '';

    /**
     * Create a new RetryableOperation instance with the given operation
     *
     * This is the starting point for the fluent interface. After creating an instance,
     * you can configure it using the fluent methods (retries(), delay(), etc.) and
     * then execute it using run().
     *
     * @param  callable  $operation  The operation to execute
     * @param  CacheInterface|null  $cache  Optional cache instance for persisting failed operations
     * @return self A new RetryableOperation instance
     */
    public static function make(callable $operation, ?CacheInterface $cache = null): self
    {
        return new self($operation, $cache);
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

                // If this is not a retryable exception or we've reached max retries
                if (! $shouldRetry || $attempt >= $this->maxRetries) {
                    // Persist the failed operation if enabled
                    if ($this->persistOnFailure && $this->cache !== null && ! empty($this->operationId)) {
                        $this->persistFailedOperation($e);
                    }

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

    /**
     * Persist a failed operation to cache
     *
     * @param  \Throwable  $exception  The exception that caused the operation to fail
     * @return bool True if the operation was persisted, false otherwise
     */
    protected function persistFailedOperation(\Throwable $exception): bool
    {
        if ($this->cache === null || empty($this->operationId)) {
            return false;
        }

        $failedOperation = [
            'operation' => $this->operation,
            'maxRetries' => $this->maxRetries,
            'delay' => $this->delay,
            'backoffFactor' => $this->backoffFactor,
            'retryableExceptions' => $this->retryableExceptions,
            'exception' => $exception,
            'timestamp' => time(),
        ];

        $key = 'failed_operation_'.$this->operationId;

        // Store the failed operation
        $success = $this->cache->set($key, $failedOperation);

        // Add the key to the list of failed operation keys
        if ($success) {
            $keys = $this->cache->get('failed_operation_keys', []);
            if (is_array($keys)) {
                if (! in_array($key, $keys)) {
                    $keys[] = $key;
                    $this->cache->set('failed_operation_keys', $keys);
                }
            } else {
                // If keys is not an array, initialize it
                $this->cache->set('failed_operation_keys', [$key]);
            }
        }

        return $success;
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

    /**
     * Enable persisting failed operations to cache
     *
     * @param  string  $operationId  A unique identifier for this operation
     */
    public function persistOnFailure(string $operationId): self
    {
        $this->persistOnFailure = true;
        $this->operationId = $operationId;

        return $this;
    }

    /**
     * Set the cache instance to use for persisting failed operations
     *
     * @param  CacheInterface  $cache  The cache instance to use
     */
    public function withCache(CacheInterface $cache): self
    {
        $this->cache = $cache;

        return $this;
    }

    /**
     * Check if a failed operation exists in the cache
     *
     * @param  string  $operationId  The operation ID to check
     * @param  CacheInterface|null  $cache  The cache instance to use (optional)
     * @return bool True if a failed operation exists, false otherwise
     */
    public static function hasFailedOperation(string $operationId, ?CacheInterface $cache = null): bool
    {
        if ($cache === null) {
            $cache = CacheFactory::make();
        }

        return $cache->has('failed_operation_'.$operationId);
    }

    /**
     * Replay a failed operation from the cache
     *
     * @param  string  $operationId  The operation ID to replay
     * @param  CacheInterface|null  $cache  The cache instance to use (optional)
     * @return mixed The result of the operation
     *
     * @throws TSDBException If the operation is not found in the cache
     * @throws \Throwable If the operation fails again
     */
    public static function replayFailedOperation(string $operationId, ?CacheInterface $cache = null): mixed
    {
        if ($cache === null) {
            $cache = CacheFactory::make();
        }

        $failedOperation = $cache->get('failed_operation_'.$operationId);
        if ($failedOperation === null) {
            throw new TSDBException("Failed operation with ID '{$operationId}' not found in cache");
        }

        if (! is_array($failedOperation) ||
            ! isset($failedOperation['operation']) ||
            ! isset($failedOperation['maxRetries']) ||
            ! isset($failedOperation['delay']) ||
            ! isset($failedOperation['backoffFactor']) ||
            ! isset($failedOperation['retryableExceptions'])) {
            throw new TSDBException("Invalid failed operation format for ID '{$operationId}'");
        }

        $operation = $failedOperation['operation'];
        $maxRetries = $failedOperation['maxRetries'];
        $delay = $failedOperation['delay'];
        $backoffFactor = $failedOperation['backoffFactor'];
        $retryableExceptions = $failedOperation['retryableExceptions'];

        if (! is_callable($operation)) {
            throw new TSDBException("Operation for ID '{$operationId}' is not callable");
        }

        // Ensure types are correct
        $maxRetries = is_numeric($maxRetries) ? (int) $maxRetries : 3;

        $delay = is_numeric($delay) ? (int) $delay : 100;

        $backoffFactor = is_numeric($backoffFactor) ? (float) $backoffFactor : 2.0;

        if (! is_array($retryableExceptions)) {
            $retryableExceptions = [\Exception::class]; // Default to Exception if invalid
        }

        /** @var array<class-string<\Throwable>> $retryableExceptions */

        // Create a new RetryableOperation instance with the same settings
        $retryable = self::make($operation, $cache)
            ->retries($maxRetries)
            ->delay($delay)
            ->backoffFactor($backoffFactor)
            ->retryableExceptions($retryableExceptions);

        // Run the operation
        $result = $retryable->run();

        // If successful, remove the failed operation from the cache
        $key = 'failed_operation_'.$operationId;
        $cache->delete($key);

        // Remove the key from the list of failed operation keys
        $keys = $cache->get('failed_operation_keys', []);
        if (is_array($keys) && ($index = array_search($key, $keys)) !== false) {
            unset($keys[$index]);
            $cache->set('failed_operation_keys', array_values($keys));
        }

        return $result;
    }

    /**
     * Get all failed operations from the cache
     *
     * @param  CacheInterface|null  $cache  The cache instance to use (optional)
     * @return array<string, array<string, mixed>> An array of failed operations, keyed by operation ID
     */
    public static function getFailedOperations(?CacheInterface $cache = null): array
    {
        if ($cache === null) {
            $cache = CacheFactory::make();
        }

        $failedOperations = [];
        $keys = $cache->get('failed_operation_keys', []);

        if (! is_array($keys)) {
            return [];
        }

        foreach ($keys as $key) {
            if (! is_string($key)) {
                continue;
            }

            $operationId = (string) str_replace('failed_operation_', '', $key);
            $failedOperation = $cache->get($key);

            if ($failedOperation !== null && is_array($failedOperation)) {
                /** @var array<string, mixed> $typedOperation */
                $typedOperation = $failedOperation;
                $failedOperations[$operationId] = $typedOperation;
            }
        }

        return $failedOperations;
    }

    public function __invoke(): mixed
    {
        return $this->run();
    }

    protected function __construct(
        callable $operation,
        ?CacheInterface $cache = null
    ) {
        $this->operation = $operation;
        $this->cache = $cache;
    }
}
