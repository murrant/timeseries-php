<?php

namespace TimeseriesPhp\Driver\RRD;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdException;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdNotFoundException;

class RrdProcess
{
    const string COMMAND_COMPLETE = 'OK u:';

    /**
     * @var array<string, string>
     */
    private array $env = [];

    private readonly InputStream $input;

    private ?Process $process = null;

    public function __construct(
        private readonly RrdConfig $config,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {
        $this->input = new InputStream;

        if ($this->config->rrdcached) {
            $this->env['RRDCACHED_ADDRESS'] = $this->config->rrdcached;
        }
    }

    public function start(): void
    {
        if ($this->process === null) {
            $this->process = new Process(
                command: [$this->config->rrdtool_exec, '-'],
                cwd: $this->config->dir,
                env: $this->env,
            );
            $this->process->setInput($this->input);
            $this->process->setTimeout($this->config->process_timeout);
            $this->process->setIdleTimeout($this->config->process_timeout);
            $this->process->start();
        }
    }

    public function stop(): void
    {
        if ($this->process) {
            $this->input->write("quit\n");
            $this->process->stop();
            $this->process = null;
        }
    }

    /**
     * @throws RrdException
     */
    public function run(string $command, string $waitFor = self::COMMAND_COMPLETE): string
    {
        $this->runAsync($command);

        if ($this->process === null) {
            throw new RrdException('RRD process failed to start');
        }

        $this->process->clearOutput();
        $this->process->waitUntil(function ($type, $buffer) use ($waitFor) {
            if ($type === Process::ERR) {
                throw new RrdException($buffer);
            }

            if (str_contains($buffer, 'ERROR: ')) {
                preg_match('/ERROR: (.*)/', $buffer, $matches);
                $error = $matches[1];
                if (str_contains($error, 'No such file')) {
                    throw new RrdNotFoundException($error);
                }
                throw new RrdException($error);
            }

            return str_contains($buffer, $waitFor);
        });

        $output = $this->process->getOutput();

        if ($waitFor === self::COMMAND_COMPLETE) {
            $output = substr($output, 0, strrpos($output, $waitFor)); // remove OK line
        }

        return rtrim($output);
    }

    public function runAsync(string $command): void
    {
        $this->start();

        // clean directory path when using rrdcached
        if ($this->config->rrdcached) {
            $command = str_replace($this->config->dir, '', $command);
        }

        $this->logger->debug('Running RRD command', ['command' => $command]);

        $this->input->write("$command\n");
    }

    public function __destruct()
    {
        $this->stop();
    }
}
