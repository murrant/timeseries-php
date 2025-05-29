<?php

namespace TimeSeriesPhp\Tests\Utils;

use PHPUnit\Framework\TestCase;
use TimeSeriesPhp\Config\LoggingConfig;
use TimeSeriesPhp\Utils\Logger;

class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset the logger configuration before each test
        Logger::configure(new LoggingConfig([
            'enabled' => true,
            'level' => 'debug',
            'log_to_file' => false,
            'log_to_stderr' => false,
            'log_to_syslog' => false,
            'log_to_error_log' => false,
        ]));
    }

    public function test_logger_configuration(): void
    {
        $config = new LoggingConfig([
            'enabled' => true,
            'level' => 'warning',
            'log_to_file' => true,
            'log_file' => '/tmp/test.log',
        ]);

        Logger::configure($config);

        $retrievedConfig = Logger::getConfig();
        $this->assertEquals('warning', $retrievedConfig->getString('level'));
        $this->assertTrue($retrievedConfig->getBool('log_to_file'));
        $this->assertEquals('/tmp/test.log', $retrievedConfig->getString('log_file'));
    }

    public function test_level_enabled(): void
    {
        $config = new LoggingConfig([
            'enabled' => true,
            'level' => 'warning',
        ]);

        $this->assertFalse($config->isLevelEnabled('debug'));
        $this->assertFalse($config->isLevelEnabled('info'));
        $this->assertTrue($config->isLevelEnabled('warning'));
        $this->assertTrue($config->isLevelEnabled('error'));
    }

    public function test_disabled_logger(): void
    {
        $config = new LoggingConfig([
            'enabled' => false,
        ]);

        $this->assertFalse($config->isLevelEnabled('debug'));
        $this->assertFalse($config->isLevelEnabled('info'));
        $this->assertFalse($config->isLevelEnabled('warning'));
        $this->assertFalse($config->isLevelEnabled('error'));
    }

    public function test_log_to_file(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'logger_test_');
        $config = new LoggingConfig([
            'enabled' => true,
            'level' => 'debug',
            'log_to_file' => true,
            'log_file' => $logFile,
        ]);

        Logger::configure($config);
        Logger::info('Test message');

        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('Test message', $logContent);
        $this->assertStringContainsString('[INFO]', $logContent);

        // Clean up
        unlink($logFile);
    }

    public function test_context_replacement(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'logger_test_');
        $config = new LoggingConfig([
            'enabled' => true,
            'level' => 'debug',
            'log_to_file' => true,
            'log_file' => $logFile,
        ]);

        Logger::configure($config);
        Logger::info('User {username} logged in', ['username' => 'john_doe']);

        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('User john_doe logged in', $logContent);

        // Clean up
        unlink($logFile);
    }

    public function test_array_context_serialization(): void
    {
        $logFile = tempnam(sys_get_temp_dir(), 'logger_test_');
        $config = new LoggingConfig([
            'enabled' => true,
            'level' => 'debug',
            'log_to_file' => true,
            'log_file' => $logFile,
        ]);

        Logger::configure($config);
        Logger::info('Data: {data}', ['data' => ['key' => 'value']]);

        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('Data: {"key":"value"}', $logContent);

        // Clean up
        unlink($logFile);
    }
}
