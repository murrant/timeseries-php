<?php

namespace App\Listeners;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Handles logging for console commands by outputting to stdout with appropriate verbosity.
 */
class ConsoleOuputVerbosityListener
{
    public function handle(CommandStarting $event): void
    {
        $handler = new class('php://stdout', Level::Debug, $event->output) extends StreamHandler {
            public function __construct(string $stream, $level, private OutputInterface $consoleOutput)
            {
                parent::__construct($stream, $level);
            }

            public function isHandling(LogRecord $record): bool
            {
                $required = match($record->level) {
                    Level::Debug => OutputInterface::VERBOSITY_DEBUG,
                    Level::Info => OutputInterface::VERBOSITY_VERY_VERBOSE,
                    Level::Notice, Level::Warning => OutputInterface::VERBOSITY_VERBOSE,
                    default => OutputInterface::VERBOSITY_NORMAL,
                };

                return $this->consoleOutput->getVerbosity() >= $required;
            }
        };

        $handler->setFormatter(new LineFormatter("[%level_name%] %message% %context% %extra%\n", null, true));

        Log::channel('stack')->pushHandler($handler);
    }
}
