<?php

namespace RRDtool;

use Exception;
use TimeSeriesPhp\Core\AbstractTimeSeriesDB;
use TimeSeriesPhp\Core\DataPoint;
use TimeSeriesPhp\Core\Query;
use TimeSeriesPhp\Core\QueryResult;

class RRDtoolDriver extends AbstractTimeSeriesDB
{
    private string $rrdDir;
    private array $rrdFiles = [];

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

        $this->connected = true;
        return true;
    }

    private function getRRDPath(string $measurement, array $tags = []): string
    {
        $filename = $measurement;

        if (!empty($tags)) {
            ksort($tags); // Ensure consistent naming
            $tagStr = implode('_', array_map(function($k, $v) {
                return "{$k}-{$v}";
            }, array_keys($tags), array_values($tags)));
            $filename .= '_' . $tagStr;
        }

        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        return $this->rrdDir . '/' . $filename . '.rrd';
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

        $cmd = 'rrdtool create ' . escapeshellarg($rrdPath) .
            ' --step ' . $step .
            ' ' . implode(' ', $dataSources) .
            ' ' . implode(' ', $archives);

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

        $cmd = 'rrdtool update ' . escapeshellarg($rrdPath) . ' ' . escapeshellarg($updateString);
        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Failed to update RRD: " . implode("\n", $output));
        }

        return true;
    }

    private function getRRDInfo(string $rrdPath): array
    {
        $cmd = 'rrdtool info ' . escapeshellarg($rrdPath);
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

    protected function buildQuery(Query $query): array
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

        return $params;
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

        $cmd = sprintf(
            'rrdtool fetch %s %s -s %s -e %s',
            escapeshellarg($params['rrd_path']),
            $params['cf'],
            is_numeric($params['start']) ? $params['start'] : escapeshellarg($params['start']),
            is_numeric($params['end']) ? $params['end'] : escapeshellarg($params['end'])
        );

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
        $params = $this->buildQuery($query);
        $serializedParams = serialize($params);
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

        $cmd = 'rrdtool create ' . escapeshellarg($rrdPath) .
            ' --step ' . $step .
            ' ' . implode(' ', $dataSources) .
            ' ' . implode(' ', $archives);

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

        $cmd = 'rrdtool graph ' . escapeshellarg($outputPath);

        foreach ($graphConfig as $option => $value) {
            if (is_array($value)) {
                foreach ($value as $v) {
                    $cmd .= ' --' . $option . ' ' . escapeshellarg($v);
                }
            } else {
                $cmd .= ' --' . $option . ' ' . escapeshellarg($value);
            }
        }

        exec($cmd . ' 2>&1', $output, $returnCode);

        if ($returnCode !== 0) {
            throw new Exception("Failed to create RRD graph: " . implode("\n", $output));
        }

        return $outputPath;
    }

    protected function formatDataPoint(DataPoint $dataPoint): string
    {
        // TODO: Implement formatDataPoint() method.
    }
}
