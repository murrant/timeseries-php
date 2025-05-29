<?php

namespace TimeSeriesPhp\Support\Logs;

enum LogLevel: string
{
    case DEBUG = 'debug';
    case INFO = 'info';
    case WARNING = 'warning';
    case ERROR = 'error';

    /**
     * Get the numeric value of the log level for comparison
     *
     * @return int The numeric value of the log level
     */
    public function getNumericValue(): int
    {
        return match ($this) {
            self::DEBUG => 0,
            self::INFO => 1,
            self::WARNING => 2,
            self::ERROR => 3,
        };
    }

    /**
     * Check if this log level is greater than or equal to the given level
     *
     * @param  LogLevel  $level  The level to compare against
     * @return bool True if this level is greater than or equal to the given level
     */
    public function isGreaterOrEqualTo(LogLevel $level): bool
    {
        return $this->getNumericValue() >= $level->getNumericValue();
    }

    /**
     * Get a log level from a string
     *
     * @param  string  $level  The string representation of the log level
     * @return LogLevel|null The log level, or null if the string is not a valid level
     */
    public static function fromString(string $level): ?LogLevel
    {
        return match (strtolower($level)) {
            'debug' => self::DEBUG,
            'info' => self::INFO,
            'warning' => self::WARNING,
            'error' => self::ERROR,
            default => null,
        };
    }
}
