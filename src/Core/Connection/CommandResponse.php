<?php

namespace TimeSeriesPhp\Core\Connection;

readonly class CommandResponse
{
    /**
     * @param  bool  $success  Whether the command was successful
     * @param  string  $data  The response data
     * @param  array<string, mixed>  $metadata  Additional metadata about the response
     * @param  string|null  $error  Error message if the command failed
     */
    public function __construct(
        public bool $success,
        public string $data = '',
        public array $metadata = [],
        public ?string $error = null
    ) {}

    /**
     * Create a successful response
     *
     * @param  string  $data  The response data
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public static function success(string $data = '', array $metadata = []): self
    {
        return new self(true, $data, $metadata);
    }

    /**
     * Create a failed response
     *
     * @param  string  $error  The error message
     * @param  array<string, mixed>  $metadata  Additional metadata
     */
    public static function failure(string $error, array $metadata = []): self
    {
        return new self(false, '', $metadata, $error);
    }
}
