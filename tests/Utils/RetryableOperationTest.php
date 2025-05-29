<?php

namespace TimeSeriesPhp\Tests\Utils;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Utils\RetryableOperation;

class RetryableOperationTest extends TestCase
{
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
}
