<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\RRD\Contracts;

use TimeseriesPhp\Core\Enum\MetricType;
use TimeseriesPhp\Core\Metrics\RetentionPolicy;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdCreationFailedException;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdNotFoundException;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdUpdateFailedException;

interface RrdtoolInterface
{
    // --- Core Operations ---

    /**
     * Create an RRD database file.
     *
     * @param  string  $path  Path to the .rrd file.
     * @param  array<string, MetricType>  $ds  DS Definitions
     * @param  RetentionPolicy[]  $retentionPolicies  Retention Policies
     *
     * @throws RrdCreationFailedException
     */
    public function create(
        string $path,
        array $ds,
        array $retentionPolicies
    ): void;

    /**
     * Update the RRD database with new values.
     *
     * @param  string  $path  Path to the .rrd file.
     * @param  array<string|int, int|float|null>  $data  Array of value(s).
     * @param  int|null  $timestamp  Timestamp for the update. If null, current time is used.
     *
     * @throws RrdUpdateFailedException
     * @throws RrdNotFoundException
     */
    public function update(string $path, array $data, ?int $timestamp = null): void;

    /**
     * Fetch data from the RRD database.
     *
     * * @param string $path Path to the .rrd file.
     * @param  string  $consolidationFunction  AVERAGE, MAX, MIN, LAST.
     * @param  array<string, string|int>  $options  Array containing 'start', 'end', 'resolution'.
     */
    public function fetch(
        string $path,
        string $consolidationFunction,
        array $options = []
    ): array;

    /**
     * Generate a graph based on RRD data.
     *
     * @param  string  $output  Output image path.
     * @param  array<string>  $options  Array of rrdgraph commands (DEF, CDEF, LINE, etc).
     */
    public function graph(string $output, array $options): array|bool;

    // --- Inspection & Metadata ---

    /** Returns the timestamp of the last update. */
    public function last(string $path): int;

    /** Returns the timestamp of the first sample in a specific RRA (default index 0). */
    public function first(string $path, int $rraIndex = 0): int;

    /**
     * Returns full structure information (DS, RRA, resolution).
     *
     * @returns array{filename: string, rrd_version: string, step: int, last_update: int, header_size: int, ds: array<string, array{index: int, type: string, minimal_heartbeat: int, min: float, max: float, last_ds: string, value: float, unknown_sec: int}>, rra: array<array{cf: string, rows: int, cur_row: int, pdp_per_row: int, xff: float, cdp_prep: array<array{value: float, unknown_datapoints: int}>}>}
     */
    public function info(string $path): array;

    /**
     * List RRD files.
     *
     * @return array<string> List of file paths.
     */
    public function listFiles(string $directory, bool $recursive = false): array;

    // --- Maintenance & Modification ---

    /**
     * Tune parameters (e.g., heartbeat, min/max values) without recreating the file.
     * Example options: ['--heartbeat', 'ds-name:600']
     */
    public function tune(string $path, array $options): bool;

    /** * Changes the size of an RRA. Note: This usually creates a 'resize.rrd' file.
     */
    public function resize(string $path, int $rraIndex, string $operation, int $rows): bool;

    // --- Data Export & Caching ---

    /**
     * Exports data to XML or JSON format.
     * Equivalent to 'rrdtool xport'.
     */
    public function xport(array $options): array;

    /**
     * Forces rrdcached to write pending updates from memory to the disk.
     */
    public function flushcache(string $path): bool;

    /**
     * Get the last error message from the backend.
     */
    public function getLastError(): string;
}
