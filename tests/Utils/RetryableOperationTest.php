<?php

namespace TimeSeriesPhp\Tests\Utils;

use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use TimeSeriesPhp\Config\CacheConfig;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Support\Cache\CacheFactory;
use TimeSeriesPhp\Utils\RetryableOperation;

class RetryableOperationTest extends TestCase
{
    protected ?CacheInterface $cache = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a memory cache for testing
        $config = new CacheConfig([
            'enabled' => true,
            'driver' => 'memory',
        ]);
        $this->cache = CacheFactory::create($config);
    }

    public function test_execute_successful_operation(): void
    {
        $result = RetryableOperation::make(function () {
            return 'success';
        })->run();

        $this->assertEquals('success', $result);
    }

    public function test_execute_with_retry(): void
    {
        $attempts = 0;
        $maxAttempts = 3;

        $result = RetryableOperation::make(function () use (&$attempts, $maxAttempts) {
            $attempts++;
            if ($attempts < $maxAttempts) {
                throw new \Exception("Attempt {$attempts} failed");
            }

            return 'success after retry';
        })
            ->retries($maxAttempts - 1) // max retries
            ->delay(10) // delay in ms
            ->backoffFactor(1.0) // backoff factor
            ->retryableExceptions([\Exception::class]) // retryable exceptions
            ->run();

        $this->assertEquals('success after retry', $result);
        $this->assertEquals($maxAttempts, $attempts);
    }

    public function test_execute_with_non_retryable_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Non-retryable exception');

        RetryableOperation::make(function () {
            throw new \RuntimeException('Non-retryable exception');
        })
            ->retries(3) // max retries
            ->delay(10) // delay in ms
            ->backoffFactor(1.0) // backoff factor
            ->retryableExceptions([\Exception::class]) // retryable exceptions (not including RuntimeException)
            ->run();
    }

    public function test_execute_with_max_retries_exceeded(): void
    {
        $attempts = 0;
        $maxRetries = 2;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Max retries exceeded');

        RetryableOperation::make(function () use (&$attempts) {
            $attempts++;
            throw new \Exception('Max retries exceeded');
        })
            ->retries($maxRetries)
            ->delay(10) // delay in ms
            ->backoffFactor(1.0) // backoff factor
            ->retryableExceptions([\Exception::class]) // retryable exceptions
            ->run();

        $this->assertEquals($maxRetries + 1, $attempts);
    }

    public function test_make_retryable(): void
    {
        $attempts = 0;
        $maxAttempts = 3;

        $operation = function (string $param) use (&$attempts, $maxAttempts) {
            $attempts++;
            if ($attempts < $maxAttempts) {
                throw new \Exception("Attempt {$attempts} failed");
            }

            return "success with param: {$param}";
        };

        // Create a retryable operation using the fluent interface
        $retryableOperation = function (string $param) use ($operation, $maxAttempts) {
            return RetryableOperation::make(fn () => $operation($param))
                ->retries($maxAttempts - 1) // max retries
                ->delay(10) // delay in ms
                ->backoffFactor(1.0) // backoff factor
                ->retryableExceptions([\Exception::class]) // retryable exceptions
                ->run();
        };

        $result = $retryableOperation('test');

        $this->assertEquals('success with param: test', $result);
        $this->assertEquals($maxAttempts, $attempts);
    }

    public function test_persist_failed_operation(): void
    {
        $operationId = 'test-persist-operation';

        // Create an operation that will always fail
        $operation = function () {
            throw new \Exception('Operation failed');
        };

        // Create a retryable operation with persistence enabled
        $retryable = RetryableOperation::make($operation, $this->cache)
            ->retries(1)
            ->delay(10)
            ->persistOnFailure($operationId);

        // Run the operation and expect it to fail
        try {
            $retryable->run();
            $this->fail('Operation should have failed');
        } catch (\Exception $e) {
            $this->assertEquals('Operation failed', $e->getMessage());
        }

        // Check if the operation was persisted
        $this->assertTrue(RetryableOperation::hasFailedOperation($operationId, $this->cache));
    }

    public function test_replay_failed_operation(): void
    {
        $operationId = 'test-replay-operation';

        // Create a mock operation that will fail
        $failingOperation = function () {
            throw new \Exception('Operation failed');
        };

        // Ensure cache is initialized
        $this->assertNotNull($this->cache, 'Cache should be initialized');

        // Create a retryable operation with persistence enabled
        $retryable = RetryableOperation::make($failingOperation, $this->cache)
            ->retries(0) // No retries, so it will fail immediately
            ->delay(10)
            ->persistOnFailure($operationId);

        // Run the operation and expect it to fail
        try {
            $retryable->run();
            $this->fail('Operation should have failed');
        } catch (\Exception $e) {
            $this->assertEquals('Operation failed', $e->getMessage());
        }

        // Check if the operation was persisted
        $this->assertTrue(RetryableOperation::hasFailedOperation($operationId, $this->cache));

        // Create a mock for the replay operation that will succeed
        // We'll manually modify the cached operation to use this function instead
        $successOperation = function () {
            return 'success';
        };

        // Ensure cache is initialized
        $this->assertNotNull($this->cache, 'Cache should be initialized');

        // Manually update the cached operation to use our success operation
        $cachedOperation = $this->cache->get('failed_operation_'.$operationId);
        $this->assertNotNull($cachedOperation);
        $this->assertIsArray($cachedOperation, 'Cached operation should be an array');
        $cachedOperation['operation'] = $successOperation;

        if ($this->cache !== null) {
            $this->assertTrue($this->cache->set('failed_operation_'.$operationId, $cachedOperation));
        } else {
            $this->fail('Cache should not be null at this point');
        }

        // Replay the failed operation
        $result = RetryableOperation::replayFailedOperation($operationId, $this->cache);

        // Check the result
        $this->assertEquals('success', $result);

        // Check that the operation was removed from the cache
        $this->assertFalse(RetryableOperation::hasFailedOperation($operationId, $this->cache));
    }

    public function test_get_failed_operations(): void
    {
        // Ensure cache is initialized
        $this->assertNotNull($this->cache, 'Cache should be initialized');

        // Clear any existing failed operations
        $this->cache->clear();

        // Create and persist multiple failed operations
        $operationIds = ['op1', 'op2', 'op3'];

        foreach ($operationIds as $id) {
            $operation = function () use ($id) {
                throw new \Exception("Operation {$id} failed");
            };

            $retryable = RetryableOperation::make($operation, $this->cache)
                ->retries(0)
                ->delay(10)
                ->persistOnFailure($id);

            try {
                $retryable->run();
            } catch (\Exception $e) {
                // Expected exception
            }
        }

        // Get all failed operations
        $failedOperations = RetryableOperation::getFailedOperations($this->cache);

        // Check that we have the expected number of failed operations
        $this->assertCount(3, $failedOperations);

        // Check that each operation ID is in the list
        foreach ($operationIds as $id) {
            $this->assertArrayHasKey($id, $failedOperations);
            /** @phpstan-ignore-next-line */
            $this->assertIsArray($failedOperations[$id], "Failed operation for ID {$id} should be an array");
            $this->assertArrayHasKey('exception', $failedOperations[$id], "Failed operation for ID {$id} should have an exception");
            $exception = $failedOperations[$id]['exception'];
            $this->assertInstanceOf(\Exception::class, $exception, "Exception for ID {$id} should be an instance of Exception");
            $this->assertEquals("Operation {$id} failed", $exception->getMessage());
        }
    }

    public function test_replay_nonexistent_operation(): void
    {
        // Ensure cache is initialized
        $this->assertNotNull($this->cache, 'Cache should be initialized');

        $this->expectException(TSDBException::class);
        $this->expectExceptionMessage("Failed operation with ID 'nonexistent' not found in cache");

        RetryableOperation::replayFailedOperation('nonexistent', $this->cache);
    }
}
