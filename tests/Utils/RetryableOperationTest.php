<?php

namespace TimeSeriesPhp\Tests\Utils;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Utils\RetryableOperation;

class RetryableOperationTest extends TestCase
{
    public function test_execute_successful_operation(): void
    {
        $result = RetryableOperation::execute(function () {
            return 'success';
        });

        $this->assertEquals('success', $result);
    }

    public function test_execute_with_retry(): void
    {
        $attempts = 0;
        $maxAttempts = 3;

        $result = RetryableOperation::execute(
            function () use (&$attempts, $maxAttempts) {
                $attempts++;
                if ($attempts < $maxAttempts) {
                    throw new \Exception("Attempt {$attempts} failed");
                }

                return 'success after retry';
            },
            $maxAttempts - 1, // max retries
            10, // delay in ms
            1.0, // backoff factor
            [\Exception::class] // retryable exceptions
        );

        $this->assertEquals('success after retry', $result);
        $this->assertEquals($maxAttempts, $attempts);
    }

    public function test_execute_with_non_retryable_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Non-retryable exception');

        RetryableOperation::execute(
            function () {
                throw new \RuntimeException('Non-retryable exception');
            },
            3, // max retries
            10, // delay in ms
            1.0, // backoff factor
            [\Exception::class] // retryable exceptions (not including RuntimeException)
        );
    }

    public function test_execute_with_max_retries_exceeded(): void
    {
        $attempts = 0;
        $maxRetries = 2;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Max retries exceeded');

        RetryableOperation::execute(
            function () use (&$attempts) {
                $attempts++;
                throw new \Exception('Max retries exceeded');
            },
            $maxRetries,
            10, // delay in ms
            1.0, // backoff factor
            [\Exception::class] // retryable exceptions
        );

        $this->assertEquals($maxRetries + 1, $attempts);
    }

    public function test_make_retryable(): void
    {
        $attempts = 0;
        $maxAttempts = 3;

        $operation = function ($param) use (&$attempts, $maxAttempts) {
            $attempts++;
            if ($attempts < $maxAttempts) {
                throw new \Exception("Attempt {$attempts} failed");
            }

            return "success with param: {$param}";
        };

        $retryableOperation = RetryableOperation::makeRetryable(
            $operation,
            $maxAttempts - 1, // max retries
            10, // delay in ms
            1.0, // backoff factor
            [\Exception::class] // retryable exceptions
        );

        $result = $retryableOperation('test');

        $this->assertEquals('success with param: test', $result);
        $this->assertEquals($maxAttempts, $attempts);
    }
}
