<?php

declare(strict_types=1);

namespace TimeSeriesPhp\Services;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use TimeSeriesPhp\Exceptions\TSDBException;
use TimeSeriesPhp\Utils\Convert;

/**
 * PSR-3 compatible logger implementation
 */
class Logger extends AbstractLogger
{
    /**
     * Log level constants mapped to their numeric values
     */
    private const LOG_LEVELS = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /**
     * @var string The minimum log level to record
     */
    private string $minLevel;

    /**
     * @var string|null The log file path (null for stderr)
     */
    private ?string $file;

    /**
     * @var int Maximum log file size before rotation
     */
    private int $maxSize;

    /**
     * @var int Maximum number of log files to keep
     */
    private int $maxFiles;

    /**
     * @var bool Whether to include timestamps in log messages
     */
    private bool $timestamps;

    /**
     * @var string Log format (simple, detailed, json)
     */
    private string $format;

    /**
     * Create a new Logger instance
     *
     * @param  array<string, mixed>  $config  The logger configuration
     */
    public function __construct(array $config)
    {
        $this->minLevel = Convert::toString($config['level'] ?? LogLevel::INFO);
        $this->file = Convert::toString($config['file'] ?? null);
        $this->maxSize = Convert::toInt($config['max_size'] ?? 10485760); // 10MB
        $this->maxFiles = Convert::toInt($config['max_files'] ?? 5);
        $this->timestamps = Convert::toBool($config['timestamps'] ?? true);
        $this->format = Convert::toString($config['format'] ?? 'simple');
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param  string|int  $level
     * @param array<string, mixed> $context
     *
     * @throws TSDBException If the log cannot be written
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Check if we should log this level
        if (! isset(self::LOG_LEVELS[$level]) || self::LOG_LEVELS[$level] < self::LOG_LEVELS[$this->minLevel]) {
            return;
        }

        $logMessage = $this->formatMessage($level, $message, $context);

        if ($this->file === null) {
            // Log to stderr
            fwrite(STDERR, $logMessage.PHP_EOL);

            return;
        }

        try {
            // At this point, we know $this->file is not null, but we need to assert this for static analysis
            assert($this->file !== null);
            $filePath = $this->file; // Create a local variable that PHPStan can track

            // Check if we need to rotate the log file
            if (file_exists($filePath) && filesize($filePath) > $this->maxSize) {
                $this->rotateLogFile();
            }

            // Ensure the directory exists
            $dir = dirname($filePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Append to the log file
            file_put_contents($filePath, $logMessage.PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            throw new TSDBException('Failed to write to log file: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Format a log message
     *
     * @param  string  $level  The log level
     * @param  string|\Stringable  $message  The log message
     * @param  array<string, mixed>  $context  The log context
     * @return string The formatted log message
     */
    private function formatMessage(string $level, string|\Stringable $message, array $context): string
    {
        $timestamp = $this->timestamps ? '['.date('Y-m-d H:i:s').'] ' : '';
        $message = (string) $message;
        $message = $this->interpolate($message, $context);

        if ($this->format === 'json') {
            return json_encode([
                'timestamp' => date('Y-m-d H:i:s'),
                'level' => $level,
                'message' => $message,
                'context' => $context,
            ]) ?: '';
        } elseif ($this->format === 'detailed') {
            return sprintf(
                '%s[%s] %s %s',
                $timestamp,
                strtoupper($level),
                $message,
                ! empty($context) ? (json_encode($context) ?: '') : ''
            );
        } else {
            // Simple format
            return sprintf('%s[%s] %s', $timestamp, strtoupper($level), $message);
        }
    }

    /**
     * Interpolate context values into the message placeholders
     *
     * @param  string  $message  The message with placeholders
     * @param  array<string, mixed>  $context  The context array
     * @return string The interpolated message
     */
    private function interpolate(string $message, array $context): string
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            if ($val === null || is_scalar($val) || (is_object($val) && method_exists($val, '__toString'))) {
                $replace['{'.$key.'}'] = $val;
            }
        }

        // Interpolate replacement values into the message and return
        return strtr($message, $replace);
    }

    /**
     * Rotate the log file
     *
     * @throws TSDBException If the log file path is null
     */
    private function rotateLogFile(): void
    {
        if ($this->file === null) {
            throw new TSDBException('Cannot rotate log file: file path is null');
        }

        // Remove the oldest log file if we've reached the maximum number of files
        $oldestLog = $this->file.'.'.$this->maxFiles;
        if (file_exists($oldestLog)) {
            unlink($oldestLog);
        }

        // Shift all existing log files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $this->file.'.'.$i;
            $newFile = $this->file.'.'.($i + 1);
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        // Rename the current log file
        rename($this->file, $this->file.'.1');
    }
}
