<?php

namespace TimeseriesPhp\Driver\RRD;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use TimeseriesPhp\Driver\RRD\Contracts\RrdtoolInterface;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdCreationFailedException;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdNotFoundException;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdUpdateFailedException;
use TimeseriesPhp\Driver\RRD\Traits\RrdCommandBuilder;
use TimeseriesPhp\Driver\RRD\Traits\RrdOutputParser;

class RrdtoolCli implements RrdtoolInterface
{
    use RrdCommandBuilder;
    use RrdOutputParser;

    public function __construct(
        private readonly RrdConfig $config,
        private readonly RrdProcess $rrd,
        private readonly LoggerInterface $logger = new NullLogger,
    ) {}

    public function fetch(string $path, string $consolidationFunction, array $options = []): array
    {
        // TODO: Implement fetch() method.
    }

    public function create(string $path, array $ds, array $retentionPolicies): void
    {
        try {
            $command = $this->buildCreateCommand($path, $ds, $retentionPolicies);
            $this->rrd->run((string) $command);
        } catch (Throwable $e) {
            throw new RrdCreationFailedException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @param  array<string|int, int|float|null>  $data
     *
     * @throws RrdNotFoundException
     * @throws RrdUpdateFailedException
     */
    public function update(string $path, array $data, ?int $timestamp = null): void
    {
        try {
            $command = $this->buildUpdateCommand($path, $data, $timestamp);
            $output = $this->rrd->run((string) $command);

            if ($output) {
                $this->logger->error($output);
            }
        } catch (RrdNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RrdUpdateFailedException($e->getMessage(), previous: $e);
        }
    }

    public function graph(string $output, array $options): array|bool
    {
        // TODO: Implement graph() method.
    }

    public function last(string $path): int
    {
        // TODO: Implement last() method.
    }

    public function first(string $path, int $rraIndex = 0): int
    {
        // TODO: Implement first() method.
    }

    /**
     * Returns full structure information (DS, RRA, resolution).
     *
     * @returns array{filename: string, rrd_version: string, step: int, last_update: int, header_size: int, ds: array<string, array{index: int, type: string, minimal_heartbeat: int, min: float, max: float, last_ds: string, value: float, unknown_sec: int}>, rra: array<array{cf: string, rows: int, cur_row: int, pdp_per_row: int, xff: float, cdp_prep: array<array{value: float, unknown_datapoints: int}>}>}
     */
    public function info(string $path): array
    {
        $command = $this->buildInfoCommand($path);
        $output = $this->rrd->run($command);

        return $this->parseInfoOutput($output);
    }

    public function dump(string $path, ?string $header = null): string
    {
        $args = [];
        if ($header !== null) {
            $args['--header'] = $header;
        }

        return $this->rrd->run(new RrdCommand('dump', $args, [$path]));
    }

    public function tune(string $path, array $options): bool
    {
        // TODO: Implement tune() method.
    }

    public function resize(string $path, int $rraIndex, string $operation, int $rows): bool
    {
        // TODO: Implement resize() method.
    }

    public function xport(array $options): array
    {
        // TODO: Implement xport() method.
    }

    public function flushCache(string $path): bool
    {
        // TODO: Implement flushCache() method.
    }

    public function getLastError(): string
    {
        // TODO: Implement getLastError() method.
    }

    /**
     * @return string[]
     */
    public function listFiles(string $directory, bool $recursive = false): array
    {
        $command = $this->buildListCommand($directory, $recursive);
        $output = $this->rrd->run($command);

        return explode("\n", $output);
    }
}
