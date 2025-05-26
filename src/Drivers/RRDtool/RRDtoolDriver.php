<?php

namespace TimeSeriesPhp\Drivers\RRDtool;

use Exception;
use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;
use TimeSeriesPhp\Drivers\RRDtool\Tags\FileNameStrategy;
use TimeSeriesPhp\Drivers\RRDtool\Tags\RRDTagStrategyContract;

class RRDtoolDriver extends AbstractTimeSeriesDB
{
    private string $rrdDir;
    private array $rrdFiles = [];
    private RRDTagStrategyContract $tagStrategy;
    private bool $useRrdcached = false;
    private string $rrdcachedAddress = '';

    public function __construct(array $config = [], ?RRDTagStrategyContract $tagStrategy = null)
    {
        parent::__construct($config);
        $this->tagStrategy = $tagStrategy ?? new FileNameStrategy();
    }

    protected function doConnect(): bool
    {
        $this->rrdDir = $this->config['rrd_dir'] ?? '/tmp/rrd';

        if (!is_dir($this->rrdDir)) {
            if (!mkdir($this->rrdDir, 0755, true)) {
                throw new Exception("Cannot create RRD directory: {$this->rrdDir}");
            }
        }

        if (!is_writable($this->rrdDir)) {
            throw new Exception("RRD directory is not writable: {$this->rrdDir}");
        }

        // Initialize rrdcached settings
        $this->useRrdcached = $this->config['use_rrdcached'] ?? false;
        $this->rrdcachedAddress = $this->config['rrdcached_address'] ?? '';

        if ($this->useRrdcached && empty($this->rrdcachedAddress)) {
            throw new Exception("rrdcached address must be specified when use_rrdcached is true");
        }

        $this->connected = true;
        return true;
    }

    /**
     * Find RRD files that have a specific tag value
     *
     * @param string $tagName The tag name to search for
     * @param string $tagValue The tag value to search for
     * @return array List of file paths that match the tag
     */
    public function findFilesByTag(string $tagName, string $tagValue): array
    {
        return $this->tagStrategy->findFilesByTag($tagName, $tagValue, $this->rrdDir);
    }

    /**
     * Find RRD files that match a set of tag values
     *
     * @param array $tags The tags as key-value pairs to search for
     * @return array List of file paths that match all the tags
     */
    public function findFilesByTags(array $tags): array
    {
        return $this->tagStrategy->findFilesByTags($tags, $this->rrdDir);
    }

    /**
     * Get the RRD file path for a measurement and tags
     * 
     * @param string $measurement The measurement name
     * @param array $tags The tags as key-value pairs
     * @return string The full path to the RRD file
     */
    private function getRRDPath(string $measurement, array $tags = []): string
    {
        return $this->tagStrategy->getFilePath($measurement, $tags, $this->rrdDir);
    }

    /**
     * Build an rrdtool command with rrdcached support if configured
     *
     * @param string $command The rrdtool command (create, update, fetch, etc.)
     * @param array $args The command arguments
     * @return string The full command string
     */
    private function buildRrdtoolCommand(string $command, array $args): string
    {
        $cmd = 'rrdtool ' . $command;

        if ($this->useRrdcached && in_array($command, ['update', 'fetch', 'info', 'last'])) {
            $cmd .= ' --daemon ' . escapeshellarg($this->rrdcachedAddress);
        }

        foreach ($args as $arg) {
            $cmd .= ' ' . $arg;
        }

        return $cmd;
    }

    private function createRRD(string $rrdPath, array $fields, int $step = 300): bool
    {
        if (file_exists($rrdPath)) {
            return true; // Already exists
        }

        // Build data source definitions
        $dataSources = [];
        foreach ($fields as $field => $value) {
            $type = $this->guessDataSourceType($value);
            $dataSources[] = "DS:{$field}:{$type}:600:U:U";
        }

        // Default archives - customize based on retention needs
        $archives = [
            'RRA:AVERAGE:0.5:1:2016',      // 5min for 1 week
            'RRA:AVERAGE:0.5:12:1488',     // 1hour for 2 months
            'RRA:AVERAGE:0.5:288:366',     // 1day for 1 year
            'RRA:MAX:0.5:1:2016',          // 5min max for 1 week
            'RRA:MAX:0.5:12:1488',         // 1hour max for 2 months
            'RRA:MIN:0.5:1:2016',          // 5min min for 1 week
            'RRA:MIN:0.5:12:1488'          // 1hour min for 2 months
        ];

        $args = [
            escapeshellarg($rrdPath),
            '--step ' . $step,
            implode(' ', $dataSources),
            implode(' ', $archives)
        ];

        $cmd = $this->buildRrdtoolCommand('create', $args);
        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Failed to create RRD: " . implode("\n", $output));
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
        if (!file_exists($rrdPath)) {
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
            $values[] = isset($fields[$dsName]) ? $fields[$dsName] : 'U'; // U = unknown/undefined
        }

        $updateString = $timestamp . ':' . implode(':', $values);

        // Build the command using the helper
        $args = [
            escapeshellarg($rrdPath),
            escapeshellarg($updateString)
        ];

        $cmd = $this->buildRrdtoolCommand('update', $args);
        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Failed to update RRD: " . implode("\n", $output));
        }

        return true;
    }

    private function getRRDInfo(string $rrdPath): array
    {
        $args = [escapeshellarg($rrdPath)];
        $cmd = $this->buildRrdtoolCommand('info', $args);
        exec($cmd . ' 2>&1', $output, $returnCode);

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
            if (preg_match('/^ds\[([^\]]+)\]\.type$/', $key, $matches)) {
                $dataSources[] = $matches[1];
            }
        }
        return $dataSources;
    }

    protected function buildQuery(Query $query): string
    {
        // RRDtool uses rrdtool fetch/graph commands, not SQL
        // We'll build parameters for rrdtool fetch

        $rrdPath = $this->getRRDPath($query->getMeasurement(), $query->getTags());

        $params = [
            'rrd_path' => $rrdPath,
            'cf' => $this->mapAggregationToConsolidationFunction($query->getAggregation()),
            'start' => $query->getStartTime() ? $query->getStartTime()->getTimestamp() : '-1h',
            'end' => $query->getEndTime() ? $query->getEndTime()->getTimestamp() : 'now',
            'fields' => $query->getFields()
        ];

        // Serialize the params to a string
        return serialize($params);
    }

    private function mapAggregationToConsolidationFunction(?string $aggregation): string
    {
        $mapping = [
            'avg' => 'AVERAGE',
            'mean' => 'AVERAGE',
            'average' => 'AVERAGE',
            'max' => 'MAX',
            'min' => 'MIN',
            'last' => 'LAST'
        ];

        return $mapping[strtolower($aggregation ?? 'average')] ?? 'AVERAGE';
    }

    protected function executeQuery(string $query): array
    {
        // This method receives the serialized params from buildQuery
        $params = unserialize($query);

        if (!file_exists($params['rrd_path'])) {
            return [];
        }

        $args = [
            escapeshellarg($params['rrd_path']),
            $params['cf'],
            '-s', is_numeric($params['start']) ? $params['start'] : escapeshellarg($params['start']),
            '-e', is_numeric($params['end']) ? $params['end'] : escapeshellarg($params['end'])
        ];

        $cmd = $this->buildRrdtoolCommand('fetch', $args);
        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("RRD fetch failed: " . implode("\n", $output));
        }

        return $this->parseRRDFetchOutput($output, $params['fields']);
    }

    private function parseRRDFetchOutput(array $output, array $requestedFields): array
    {
        $result = [];
        $headers = [];

        foreach ($output as $i => $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // First non-empty line contains headers
            if (empty($headers) && !preg_match('/^\d+:/', $line)) {
                $headers = preg_split('/\s+/', $line);
                continue;
            }

            // Data lines start with timestamp
            if (preg_match('/^(\d+):\s*(.+)$/', $line, $matches)) {
                $timestamp = (int)$matches[1];
                $values = preg_split('/\s+/', trim($matches[2]));

                $dataPoint = ['time' => date('c', $timestamp)];

                foreach ($headers as $j => $header) {
                    if (isset($values[$j]) && $values[$j] !== 'nan') {
                        // Only include requested fields or all if none specified
                        if (in_array('*', $requestedFields) ||
                            in_array($header, $requestedFields) ||
                            empty($requestedFields)) {
                            $dataPoint[$header] = is_numeric($values[$j]) ? (float)$values[$j] : $values[$j];
                        }
                    }
                }

                $result[] = $dataPoint;
            }
        }

        return $result;
    }

    public function query(Query $query): QueryResult
    {
        $serializedParams = $this->buildQuery($query);
        $params = unserialize($serializedParams);
        $result = $this->executeQuery($serializedParams);

        // Apply limit if specified
        if ($query->getLimit() && count($result) > $query->getLimit()) {
            $result = array_slice($result, 0, $query->getLimit());
        }

        return new QueryResult($result, ['rrd_path' => $params['rrd_path']]);
    }

    public function rawQuery(string $query): QueryResult
    {
        // For RRDtool, raw query would be a direct rrdtool command
        exec($query . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("RRD command failed: " . implode("\n", $output));
        }

        // Try to parse as fetch output, otherwise return raw
        try {
            $parsed = $this->parseRRDFetchOutput($output, ['*']);
            return new QueryResult($parsed);
        } catch (Exception $e) {
            return new QueryResult([['raw_output' => implode("\n", $output)]]);
        }
    }

    public function createDatabase(string $database): bool
    {
        // RRDtool doesn't have databases, but we can create a subdirectory
        $dbDir = $this->rrdDir . '/' . $database;

        if (!is_dir($dbDir)) {
            return mkdir($dbDir, 0755, true);
        }

        return true;
    }

    public function listDatabases(): array
    {
        $databases = [];
        $items = scandir($this->rrdDir);

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($this->rrdDir . '/' . $item)) {
                $databases[] = $item;
            }
        }

        return $databases;
    }

    public function close(): void
    {
        $this->connected = false;
        $this->rrdFiles = [];
    }

    // RRDtool-specific methods
    public function createRRDWithCustomConfig(string $measurement, array $tags, array $config): bool
    {
        $rrdPath = $this->getRRDPath($measurement, $tags);

        $step = $config['step'] ?? 300;
        $dataSources = $config['data_sources'] ?? [];
        $archives = $config['archives'] ?? [];

        if (empty($dataSources)) {
            throw new Exception("Data sources must be specified for custom RRD creation");
        }

        if (empty($archives)) {
            throw new Exception("Archives must be specified for custom RRD creation");
        }

        $args = [
            escapeshellarg($rrdPath),
            '--step ' . $step,
            implode(' ', $dataSources),
            implode(' ', $archives)
        ];

        $cmd = $this->buildRrdtoolCommand('create', $args);
        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Failed to create custom RRD: " . implode("\n", $output));
        }

        return true;
    }

    public function getRRDGraph(string $measurement, array $tags, array $graphConfig): string
    {
        $rrdPath = $this->getRRDPath($measurement, $tags);
        $outputPath = $this->rrdDir . '/graph_' . uniqid() . '.png';

        $args = [escapeshellarg($outputPath)];

        foreach ($graphConfig as $option => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $args[] = '--' . $option . ' ' . escapeshellarg($v);
                }
            } else {
                $args[] = '--' . $option . ' ' . escapeshellarg($value);
            }
        }

        $cmd = $this->buildRrdtoolCommand('graph', $args);
        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Failed to create RRD graph: " . implode("\n", $output));
        }

        return $outputPath;
    }

    protected function formatDataPoint(DataPoint $dataPoint): string
    {
        // Format the data point for RRDtool update command
        $timestamp = $dataPoint->getTimestamp()->getTimestamp();
        $values = [];

        foreach ($dataPoint->getFields() as $field => $value) {
            $values[] = $field . ':' . $value;
        }

        return $timestamp . ':' . implode(':', array_values($dataPoint->getFields()));
    }
}
