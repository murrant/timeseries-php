<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use Exception;
use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Core\RawQueryContract;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyContract;
use TimeSeriesPhp\Exceptions\ConnectionException;
use TimeSeriesPhp\Exceptions\QueryException;
use TimeSeriesPhp\Exceptions\RRDtoolPrematureUpdateException;
use TimeSeriesPhp\Exceptions\WriteException;

class RRDtoolDriver extends AbstractTimeSeriesDB
{
    private string $rrdDir;

    private string $rrdtoolPath = 'rrdtool';

    private bool $useRrdcached = false;

    private string $rrdcachedAddress = '';

    private RRDTagStrategyContract $tagStrategy;

    /**
     * @throws ConnectionException
     */
    protected function doConnect(): bool
    {
        $this->rrdDir = $this->config->get('rrd_dir');
        $this->rrdtoolPath = $this->config->get('rrdtool_path');
        $this->useRrdcached = $this->config->get('use_rrdcached');
        $this->rrdcachedAddress = $this->config->get('rrdcached_address');

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

        $tagStrategyClass = $this->config->get('tag_strategy');
        $this->tagStrategy = new $tagStrategyClass($this->rrdDir);
        $this->queryBuilder = new RRDtoolQueryBuilder($this->tagStrategy);

        $this->connected = true;

        return true;
    }

    /**
     * Find RRD files that match a set of tag values
     *
     * @param  array<string, string>  $tags  The tags as key-value pairs to search for
     * @param  string|null  $measurement  Optional measurement name to filter by
     * @return array List of file paths that match all the tags
     */
    public function findFilesByTags(array $tags, ?string $measurement = null): array
    {
        // Convert tags to TagCondition objects
        $tagConditions = [];
        foreach ($tags as $tag => $value) {
            $tagConditions[] = new Tags\TagCondition($tag, '=', $value);
        }

        return $this->tagStrategy->resolveFilePaths($measurement ?? '*', $tagConditions);
    }

    /**
     * Get the RRD file path for a measurement and tags
     *
     * @param  string  $measurement  The measurement name
     * @param  array  $tags  The tags as key-value pairs
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
     * @param  array  $args  The command arguments
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

    private function createRRD(string $rrdPath, array $fields, ?int $step = null): bool
    {
        if (file_exists($rrdPath)) {
            return true; // Already exists
        }

        // Use default step from config if not provided
        $step = $step ?? $this->config->get('default_step');

        // Build data source definitions
        $dataSources = [];
        foreach ($fields as $field => $value) {
            $type = $this->guessDataSourceType($value);
            $dataSources[] = "DS:{$field}:{$type}:600:U:U";
        }

        // Use default archives from config
        $archives = $this->config->get('default_archives');

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

        return true;
    }

    private function guessDataSourceType($value): string
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

    private function parseRRDXportJson(array $json, array $requestedFields): QueryResult
    {
        $allFields = in_array('*', $requestedFields);
        $legend = array_filter($json['meta']['legend'], function ($field) use ($requestedFields) {
            return in_array('*', $requestedFields)
                || in_array($field, $requestedFields);
        });
        $start = $json['meta']['start'];
        $step = $json['meta']['step'];
        $series = [];

        foreach ($json['data'] as $index => $values) {
            $entry = ['time' => $start + $step * $index];
            foreach ($legend as $field => $name) {
                $entry[$name] = $values[$field];
            }
            $series[] = $entry;
        }

        return new QueryResult($series, $json['meta']);
    }

    public function rawQuery(RawQueryContract $query): QueryResult
    {
        if (! $query instanceof RRDtoolRawQuery) {
            throw new QueryException($query, 'Invalid query type');
        }

        // For RRDtool, raw query would be a direct rrdtool command
        exec($this->rrdtoolPath.' '.$query->getRawQuery().' 2>&1', $output, $returnCode);

        $output = implode("\n", $output);
        if ($returnCode !== 0) {
            throw new QueryException($query, 'RRD command failed: '.$output, $returnCode);
        }

        if ($query->type === 'xport') {
            $json = json_decode($output, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new QueryException($query, 'Failed to parse RRD command output: '.json_last_error_msg().PHP_EOL.$output, json_last_error());
            }

            return $this->parseRRDXportJson($json, ['*']);
        }

        return new QueryResult([['raw_output' => $output]]);
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
    public function createRRDWithCustomConfig(string $measurement, array $tags, array $config): bool
    {
        $rrdPath = $this->getRRDPath($measurement, $tags);

        $step = $config['step'] ?? $this->config->get('default_step');
        $dataSources = $config['data_sources'] ?? [];
        $archives = $config['archives'] ?? $this->config->get('default_archives');

        if (empty($dataSources)) {
            throw new Exception('Data sources must be specified for custom RRD creation');
        }

        if (empty($archives)) {
            throw new Exception('Archives must be specified for custom RRD creation');
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
            throw new Exception('Failed to create custom RRD: '.implode("\n", $output));
        }

        return true;
    }

    public function getRRDGraph(string $measurement, array $tags, array $graphConfig): string
    {
        $rrdPath = $this->getRRDPath($measurement, $tags);
        $outputPath = $this->rrdDir.'/graph_'.uniqid().'.png';

        $args = [escapeshellarg($outputPath)];

        foreach ($graphConfig as $option => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $args[] = '--'.$option.' '.escapeshellarg($v);
                }
            } else {
                $args[] = '--'.$option.' '.escapeshellarg($value);
            }
        }

        $cmd = $this->buildRrdtoolCommand('graph', $args);
        exec($cmd.' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception('Failed to create RRD graph: '.implode("\n", $output));
        }

        return $outputPath;
    }
}
