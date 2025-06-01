<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Exception;

use TimeSeriesPhp\Exceptions\TSDBException;

class RRDtoolException extends TSDBException
{
    private string $output = '';

    private string $errorOutput = '';

    /**
     * @param  string[]  $args
     */
    public function __construct(
        public readonly string $command,
        public readonly array $args = [],
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $message = $message ?: "RRDtool Command '$command' failed with code $code";
        parent::__construct($message, $code, $previous);
    }

    public function setOutput(string $output): self
    {
        $this->output = $output;

        return $this;
    }

    public function setErrorOutput(string $output): self
    {
        $this->errorOutput = $output;

        return $this;
    }

    public function getOutput(): string
    {
        return $this->errorOutput;
    }

    public function getErrorOutput(): string
    {
        return $this->errorOutput;
    }

    public function getDebugMessage(bool $debug = false): string
    {
        if ($debug) {
            $message = $this->getMessage();
            $message .= "\n\nCommand: {$this->command}\n";
            $message .= 'Args: '.implode(' ', $this->args)."\n";
            $message .= "Output: {$this->output}\n";
            $message .= "Error output: {$this->errorOutput}\n";

            return $message;
        }

        return $this->getMessage();
    }
}
