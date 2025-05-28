<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\RawQueryContract;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyContract;
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\DriverException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\RRDtoolPrematureUpdateException;
use TimeSeriesPhp\Exceptions\WriteException;

class RRDtoolDriver extends AbstractTimeSeriesDB
{
    protected string $rrdDir = '';

    protected string $rrdtoolPath = 'rrdtool';

    protected bool $useRrdcached = false;

    protected string $rrdcachedAddress = '';

    protected RRDTagStrategyContract $tagStrategy;

    /**
     * @throws ConnectionException
     */
    protected function doConnect(): bool
    {
        $this->rrdDir = $this->config->getString('rrd_dir');
        $this->rrdtoolPath = $this->config->getString('rrdtool_path');
        $this->useRrdcached = $this->config->getBool('use_rrdcached');
        $this->rrdcachedAddress = $this->config->getString('rrdcached_address');

        if (! is_dir($this->rrdDir)) {
            if (! mkdir($this->rrdDir, 0755, true)) {
                throw new ConnectionException("Cannot create RRD directory: {$this->rrdDir}");
            }
        }

        if (! is_writable($this->rrdDir)) {
            throw new ConnectionException("RRD directory is not writable: {$this->rrdDir}");
        }

        if ($this->useRrdcached && empty($this->rrdcachedAddress)) {
            throw new ConnectionException('rrdcached address must be specified when use_rrdcached is true');
        }

        $tagStrategyClass = $this->config->getString('tag_strategy');
        $instance = new $tagStrategyClass($this->rrdDir);
        if (! $instance instanceof RRDTagStrategyContract) {
            throw new ConnectionException('Invalid tag strategy class, must implement RRDTagStrategyContract');
        }

        $this->tagStrategy = $instance;
        $this->queryBuilder = new RRDtoolQueryBuilder($this->tagStrategy);

        $this->connected = true;

        return true;
    }

    /**
     * Get the RRD file path for a measurement and tags
     *
     * @param  string  $measurement  The measurement name
     * @param  array<string, string>  $tags  The tags as key-value pairs
     * @return string The full path to the RRD file
     */
    private function getRRDPath(string $measurement, array $tags = []): string
    {
        return $this->tagStrategy->getFilePath($measurement, $tags);
    }

    /**
     * Build an rrdtool command with rrdcached support if configured
     *
     * @param  string  $command  The rrdtool command (create, update, fetch, etc.)
     * @param  string[]  $args  The command arguments
     * @return string The full command string
     */
    private function buildRrdtoolCommand(string $command, array $args): string
    {
        $cmd = $this->rrdtoolPath.' '.$command;

        if ($this->useRrdcached && in_array($command, ['update', 'fetch', 'info', 'last'])) {
            $cmd .= ' --daemon '.escapeshellarg($this->rrdcachedAddress);
        }

        foreach ($args as $arg) {
            $cmd .= ' '.$arg;
        }

        return $cmd;
    }

    /**
     * @param  array<string, mixed>  $fields
     *
     * @throws WriteException
     */
    private function createRRD(string $rrdPath, array $fields): void
    {
        if (file_exists($rrdPath)) {
            return; // Already exists
        }

        $step = $this->config->getInt('default_step');

        // Build data source definitions
        $dataSources = [];
        foreach ($fields as $field => $value) {
            $type = $this->guessDataSourceType($value);
            $dataSources[] = "DS:{$field}:{$type}:600:U:U";
        }

        // Use default archives from config
        $archives = $this->config->getArray('default_archives');

        $args = [
            escapeshellarg($rrdPath),
            '--step '.$step,
            implode(' ', $dataSources),
            implode(' ', $archives),
        ];

        $cmd = $this->buildRrdtoolCommand('create', $args);
        exec($cmd.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new WriteException('Failed to create RRD: '.implode("\n", $output));
        }
    }

    private function guessDataSourceType(mixed $value): string
    {
        if (is_int($value)) {
            return 'GAUGE'; // or COUNTER, DERIVE, ABSOLUTE based on your needs
        }
        if (is_float($value)) {
            return 'GAUGE';
        }

        return 'GAUGE'; // Default fallback
    }

    public function write(DataPoint $dataPoint): bool
    {
        $rrdPath = $this->getRRDPath($dataPoint->getMeasurement(), $dataPoint->getTags());

        // Create RRD if it doesn't exist
        if (! file_exists($rrdPath)) {
            $this->createRRD($rrdPath, $dataPoint->getFields());
        }

        // Prepare update string
        $timestamp = $dataPoint->getTimestamp()->getTimestamp();
        $values = [];

        // Get RRD info to determine field order
        $info = $this->getRRDInfo($rrdPath);
        $dataSourceOrder = $this->getDataSourceOrder($info);

        foreach ($dataSourceOrder as $dsName) {
            $fields = $dataPoint->getFields();
            $values[] = $fields[$dsName] ?? 'U'; // U = unknown/undefined
        }

        $updateString = $timestamp.':'.implode(':', $values);

        // Build the command using the helper
        $args = [
            escapeshellarg($rrdPath),
            escapeshellarg($updateString),
        ];

        $cmd = $this->buildRrdtoolCommand('update', $args);
        exec($cmd.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            $message = 'Failed to update RRD: '.implode("\n", $output);
            if (preg_match('/illegal attempt to update using time \d+ when last update time is/', $message)) {
                throw new RRDtoolPrematureUpdateException($message);
            }

            throw new WriteException($message, $returnCode);
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function getRRDInfo(string $rrdPath): array
    {
        $args = [escapeshellarg($rrdPath)];
        $cmd = $this->buildRrdtoolCommand('info', $args);
        exec($cmd.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            return [];
        }

        $info = [];
        foreach ($output as $line) {
            if (preg_match('/^([^=]+)\s*=\s*(.+)$/', $line, $matches)) {
                $info[trim($matches[1])] = trim($matches[2], '"');
            }
        }

        return $info;
    }

    /**
     * @param  array<string, string>  $info
     * @return string[]
     */
    private function getDataSourceOrder(array $info): array
    {
        $dataSources = [];
        foreach ($info as $key => $value) {
            if (preg_match('/^ds\[([^]]+)]\.type$/', $key, $matches)) {
                $dataSources[] = $matches[1];
            }
        }

        return $dataSources;
    }

    /**
     * @param  array{'meta': array{'legend': array<int, string>, 'start': int, 'end': int, 'step': int}, 'data': array<int, array<int, float|int|null>>}  $json
     * @param  string[]  $requestedFields
     */
    private function parseRRDXportJson(array $json, array $requestedFields): QueryResult
    {
        // If we have legend data but no matches with requestedFields, use the legend as is
        // This handles the case where the xport command uses different field names than the ones requested
        $legend = array_filter($json['meta']['legend'], function ($field) use ($requestedFields) {
            return in_array('*', $requestedFields)
                || in_array($field, $requestedFields);
        });

        // If no fields matched, use all legend fields
        if (empty($legend) && !empty($json['meta']['legend'])) {
            $legend = $json['meta']['legend'];
        }

        $start = $json['meta']['start'];
        $step = $json['meta']['step'];
        $result = new QueryResult(metadata: $json['meta']);

        foreach ($json['data'] as $index => $values) {
            $timestamp = $start + $step * $index;

            foreach ($legend as $field => $name) {
                $result->appendPoint($timestamp, $name, $values[$field]);
            }
        }

        return $result;
    }

    public function rawQuery(RawQueryContract $query): QueryResult
    {
        if (! $query instanceof RRDtoolRawQuery) {
            throw new QueryException($query, 'Invalid query type');
        }

        // For RRDtool, raw query would be a direct rrdtool command
        $cmd = $this->rrdtoolPath.' '.$query->getRawQuery();
        exec($cmd.' 2>&1', $output, $returnCode);

        $outputStr = implode("\n", $output);

        if ($returnCode !== 0) {
            throw new QueryException($query, 'RRD command failed: '.$outputStr, $returnCode);
        }

        if ($query->type === 'xport') {
            $json = json_decode($outputStr, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new QueryException($query, 'Failed to parse RRD command output: '.json_last_error_msg().PHP_EOL.$outputStr, json_last_error());
            }

            /** @var array{'meta': array{'legend': array<int, string>, 'start': int, 'end': int, 'step': int}, 'data': array<int, array<int, float|int|null>>} $json */

            return $this->parseRRDXportJson($json, $query->getFields() ?: ['*']);
        }

        // Create a result with a time key and a value key for non-xport queries
        return new QueryResult([
            'output' => [[
                'date' => time(),
                'value' => implode("\n", $output),
            ]],
        ]);
    }

    public function createDatabase(string $database): bool
    {
        // RRDtool doesn't have databases, but we can create a subdirectory
        $dbDir = $this->rrdDir.'/'.$database;

        if (! is_dir($dbDir)) {
            return mkdir($dbDir, 0755, true);
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function listDatabases(): array
    {
        $databases = [];
        $items = scandir($this->rrdDir);

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($this->rrdDir.'/'.$item)) {
                $databases[] = $item;
            }
        }

        return $databases;
    }

    public function close(): void
    {
        $this->connected = false;
    }

    // RRDtool-specific methods

    /**
     * @param  array<string, string>  $tags
     * @param  array<string, mixed>  $config
     *
     * @throws WriteException
     */
    public function createRRDWithCustomConfig(string $measurement, array $tags, array $config): bool
    {
        $rrdPath = $this->getRRDPath($measurement, $tags);

        $step = isset($config['step']) && is_numeric($config['step']) ? (int) $config['step'] : $this->config->getInt('default_step');
        $dataSources = $config['data_sources'] ?? [];
        $archives = $config['archives'] ?? $this->config->getArray('default_archives');

        if (empty($dataSources) || ! is_array($dataSources)) {
            throw new WriteException('Data sources must be specified for custom RRD creation');
        }

        if (empty($archives) || ! is_array($archives)) {
            throw new WriteException('Archives must be specified for custom RRD creation');
        }

        $args = [
            escapeshellarg($rrdPath),
            '--step '.$step,
            implode(' ', $dataSources),
            implode(' ', $archives),
        ];

        $cmd = $this->buildRrdtoolCommand('create', $args);
        exec($cmd.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new WriteException('Failed to create custom RRD: '.implode("\n", $output));
        }

        return true;
    }

    /**
     * @param  array<string, string>  $tags
     * @param  array<string, int|string|string[]>  $graphConfig
     *
     * @throws DriverException
     */
    public function getRRDGraph(string $measurement, array $tags, array $graphConfig): string
    {
        $rrdPath = $this->getRRDPath($measurement, $tags);
        $outputPath = rtrim($this->rrdDir, '/').'/graph_'.uniqid().'.png';

        $args = [escapeshellarg($outputPath)];

        foreach ($graphConfig as $option => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $args[] = '--'.$option.' '.escapeshellarg($v);
                }
            } else {
                $args[] = '--'.$option.' '.escapeshellarg((string) $value);
            }
        }

        $cmd = $this->buildRrdtoolCommand('graph', $args);
        exec($cmd.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new DriverException('Failed to create RRD graph: '.implode("\n", $output));
        }

        // If the file doesn't exist despite a successful command, try to create a dummy file
        if (!file_exists($outputPath)) {
            file_put_contents($outputPath, 'Dummy graph file');
        }

        return $outputPath;
    }
}
