<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Tests\Services;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use TimeSeriesPhp\Services\Logger;

class LoggerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = sys_get_temp_dir().'/tsdb_test_log_'.uniqid().'.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    public function test_log_to_file(): void
    {
        $config = [
            'level' => LogLevel::INFO,
            'file' => $this->tempFile,
            'timestamps' => false,
            'format' => 'simple',
        ];

        $logger = new Logger($config);
        $logger->info('Test message');

        $this->assertFileExists($this->tempFile);
        $content = file_get_contents($this->tempFile);
        $this->assertNotFalse($content, 'Failed to read log file');
        $this->assertStringContainsString('[INFO] Test message', $content);
    }

    public function test_log_level_filtering(): void
    {
        $config = [
            'level' => LogLevel::WARNING,
            'file' => $this->tempFile,
            'timestamps' => false,
            'format' => 'simple',
        ];

        $logger = new Logger($config);
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');

        $this->assertFileExists($this->tempFile);
        $content = file_get_contents($this->tempFile);
        $this->assertNotFalse($content, 'Failed to read log file');

        $this->assertStringNotContainsString('Debug message', $content);
        $this->assertStringNotContainsString('Info message', $content);
        $this->assertStringContainsString('Warning message', $content);
        $this->assertStringContainsString('Error message', $content);
    }

    public function test_log_with_context(): void
    {
        $config = [
            'level' => LogLevel::INFO,
            'file' => $this->tempFile,
            'timestamps' => false,
            'format' => 'simple',
        ];

        $logger = new Logger($config);
        $logger->info('User {username} logged in', ['username' => 'john_doe']);

        $this->assertFileExists($this->tempFile);
        $content = file_get_contents($this->tempFile);
        $this->assertNotFalse($content, 'Failed to read log file');
        $this->assertStringContainsString('[INFO] User john_doe logged in', $content);
    }

    public function test_json_format(): void
    {
        $config = [
            'level' => LogLevel::INFO,
            'file' => $this->tempFile,
            'format' => 'json',
        ];

        $logger = new Logger($config);
        $logger->info('Test message');

        $this->assertFileExists($this->tempFile);
        $content = file_get_contents($this->tempFile);
        $this->assertNotFalse($content, 'Failed to read log file');

        $logEntry = json_decode($content, true);
        $this->assertIsArray($logEntry);
        $this->assertEquals('info', $logEntry['level']);
        $this->assertEquals('Test message', $logEntry['message']);
    }

    public function test_detailed_format(): void
    {
        $config = [
            'level' => LogLevel::INFO,
            'file' => $this->tempFile,
            'timestamps' => false,
            'format' => 'detailed',
        ];

        $logger = new Logger($config);
        $logger->info('Test message', ['user' => 'john']);

        $this->assertFileExists($this->tempFile);
        $content = file_get_contents($this->tempFile);
        $this->assertNotFalse($content, 'Failed to read log file');

        $this->assertStringContainsString('[INFO] Test message {"user":"john"}', $content);
    }
}
