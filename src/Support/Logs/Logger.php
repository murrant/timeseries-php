<?php

namespace TimeSeriesPhp\Support\Logs;

use TimeSeriesPhp\Support\Config\LoggingConfig;

/**
 * Simple logger for the TimeSeriesPhp library.
 */
class Logger
{
    // Constants kept for backward compatibility
    public const LEVEL_DEBUG = 'debug';

    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    public const LEVEL_ERROR = 'error';

    private static ?LoggingConfig $config = null;

    /**
     * Configure the logger
     *
     * @param  LoggingConfig  $config  The logging configuration
     */
    public static function configure(LoggingConfig $config): void
    {
        self::$config = $config;
    }

    /**
     * Get the current logging configuration
     *
     * @return LoggingConfig The current logging configuration
     */
    public static function getConfig(): LoggingConfig
    {
        if (self::$config === null) {
            self::$config = new LoggingConfig;
        }

        return self::$config;
    }

    /**
     * Log a debug message
     *
     * @param  string  $message  The message to log
     * @param  array<string, mixed>  $context  Additional context data
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log an info message
     *
     * @param  string  $message  The message to log
     * @param  array<string, mixed>  $context  Additional context data
     */
    public static function info(string $message, array $context = []): void
    {
        self::log(LogLevel::INFO, $message, $context);
    }

    /**
     * Log a warning message
     *
     * @param  string  $message  The message to log
     * @param  array<string, mixed>  $context  Additional context data
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Log an error message
     *
     * @param  string  $message  The message to log
     * @param  array<string, mixed>  $context  Additional context data
     */
    public static function error(string $message, array $context = []): void
    {
        self::log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Log a message with the specified level
     *
     * @param  LogLevel  $level  The log level
     * @param  string  $message  The message to log
     * @param  array<string, mixed>  $context  Additional context data
     */
    public static function log(LogLevel $level, string $message, array $context = []): void
    {
        $config = self::getConfig();

        // Check if this level is enabled
        if (! $config->isLevelEnabled($level)) {
            return;
        }

        // Format the message
        $formattedMessage = self::formatMessage($level, $message, $context);

        // Log to the configured destinations
        if ($config->getBool('log_to_file')) {
            self::logToFile($formattedMessage, $config->getString('log_file'));
        }

        if ($config->getBool('log_to_stderr')) {
            self::logToStderr($formattedMessage);
        }

        if ($config->getBool('log_to_syslog')) {
            self::logToSyslog($formattedMessage);
        }

        if ($config->getBool('log_to_error_log')) {
            error_log($formattedMessage);
        }
    }

    /**
     * Format a log message
     *
     * @param  LogLevel  $level  The log level
     * @param  string  $message  The message to log
     * @param  array<string, mixed>  $context  Additional context data
     * @return string The formatted message
     */
    private static function formatMessage(LogLevel $level, string $message, array $context = []): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level->value);

        // Replace placeholders in the message
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $replacements['{'.$key.'}'] = $value;
        }
        $message = strtr($message, $replacements);

        return "[$timestamp] [$levelUpper] $message";
    }

    /**
     * Log a message to a file
     *
     * @param  string  $message  The formatted message
     * @param  string  $file  The log file path
     */
    private static function logToFile(string $message, string $file): void
    {
        $dir = dirname($file);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, $message.PHP_EOL, FILE_APPEND);
    }

    /**
     * Log a message to stderr
     *
     * @param  string  $message  The formatted message
     */
    private static function logToStderr(string $message): void
    {
        fwrite(STDERR, $message.PHP_EOL);
    }

    /**
     * Log a message to syslog
     *
     * @param  string  $message  The formatted message
     */
    private static function logToSyslog(string $message): void
    {
        syslog(LOG_INFO, $message);
    }
}
