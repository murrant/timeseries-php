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

    protected ?\Throwable $lastException = null;

    protected function __construct(
        callable $operation,
        ?CacheInterface $cache = null
    ) {
        $this->operation = $operation;
        $this->cache = $cache;
    }

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

        // Store the operation and exception in the object
        $this->lastException = $exception;

        $key = 'failed_operation_'.$this->operationId;

        // Store the serialized operation directly in cache
        $success = $this->cache->set($key, $this);

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

        $key = 'failed_operation_'.$operationId;
        $retryable = $cache->get($key);

        if ($retryable === null) {
            throw new TSDBException("Failed operation with ID '{$operationId}' not found in cache");
        }

        if (! $retryable instanceof self) {
            throw new TSDBException("Invalid failed operation format for ID '{$operationId}'");
        }

        // Set the cache which wasn't serialized
        $retryable->cache = $cache;

        // Run the operation
        $result = $retryable->run();

        // If successful, remove the failed operation from the cache
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

            $operationId = str_starts_with($key, 'failed_operation_') ? substr($key, strlen('failed_operation_')) : $key;
            $failedOperation = $cache->get($key);

            if ($failedOperation !== null && $failedOperation instanceof self) {
                // Convert the RetryableOperation object to an array for backward compatibility with tests
                $failedOperations[$operationId] = [
                    'operation' => $failedOperation->getOperation(),
                    'exception' => $failedOperation->getLastException(),
                    'timestamp' => time(), // We don't store timestamp anymore, so use current time
                ];
            }
        }

        return $failedOperations;
    }

    public function __invoke(): mixed
    {
        return $this->run();
    }

    /**
     * Get the operation
     *
     * @return callable The operation
     */
    public function getOperation(): callable
    {
        return $this->operation;
    }

    /**
     * Get the last exception
     *
     * @return \Throwable|null The last exception
     */
    public function getLastException(): ?\Throwable
    {
        return $this->lastException;
    }

    /**
     * Serialize the object
     *
     * @return array<string, mixed> The serialized object
     */
    public function __serialize(): array
    {
        return [
            'maxRetries' => $this->maxRetries,
            'delay' => $this->delay,
            'backoffFactor' => $this->backoffFactor,
            'retryableExceptions' => $this->retryableExceptions,
            'persistOnFailure' => $this->persistOnFailure,
            'operationId' => $this->operationId,
            'lastException' => $this->lastException,
            // Note: We don't serialize the operation or cache as they might not be serializable
        ];
    }

    /**
     * Unserialize the object
     *
     * @param  array<string, mixed>  $data  The serialized data
     */
    public function __unserialize(array $data): void
    {
        $this->maxRetries = isset($data['maxRetries']) && is_int($data['maxRetries'])
            ? $data['maxRetries']
            : 3;

        $this->delay = isset($data['delay']) && is_int($data['delay'])
            ? $data['delay']
            : 100;

        $this->backoffFactor = isset($data['backoffFactor']) && is_float($data['backoffFactor'])
            ? $data['backoffFactor']
            : 2.0;

        /** @var array<class-string<\Throwable>> $exceptions */
        $exceptions = isset($data['retryableExceptions']) && is_array($data['retryableExceptions'])
            ? $data['retryableExceptions']
            : [\Exception::class];
        $this->retryableExceptions = $exceptions;

        $this->persistOnFailure = isset($data['persistOnFailure']) && is_bool($data['persistOnFailure']) && $data['persistOnFailure'];

        $this->operationId = isset($data['operationId']) && is_string($data['operationId'])
            ? $data['operationId']
            : '';

        $this->lastException = isset($data['lastException']) && $data['lastException'] instanceof \Throwable
            ? $data['lastException']
            : null;

        // Note: The operation and cache need to be set separately after unserializing
    }
}
