<?php

namespace TimeSeriesPhp\Core\Driver\Formatter;

use TimeSeriesPhp\Core\Data\DataPoint;

class LineProtocolFormatter
{
    /**
     * Convert data array to line protocol string
     *
     * @param  DataPoint  $data  Format: [
     *                           'measurement' => 'cpu_usage',
     *                           'tags' => ['host' => 'server01', 'region' => 'us-west'],
     *                           'fields' => ['value' => 85.2, 'count' => 10],
     *                           'timestamp' => 1640995200000000000 // nanoseconds
     *                           ]
     */
    public function format(DataPoint $data): string
    {
        $line = $this->escapeMeasurement($data->getMeasurement());

        // Add tags
        foreach ($data->getTags() as $key => $value) {
            $line .= ','.$this->escapeTagKey($key).'='.$this->escapeTagValue($value);
        }

        // Add fields
        $fields = [];
        foreach ($data->getFields() as $key => $value) {
            if ($value !== null) {
                $fields[] = $this->escapeFieldKey($key).'='.$this->formatFieldValue($value);
            }
        }
        $line .= ' '.implode(',', $fields);

        // Add timestamp
        $line .= ' '.$data->getTimestamp()->getTimestamp();

        return $line;
    }

    /**
     * Format multiple data points
     *
     * @param  DataPoint[]  $dataPoints
     */
    public function formatBatch(array $dataPoints): string
    {
        return implode("\n", array_map(fn ($dp) => $this->format($dp), $dataPoints));
    }

    private function escapeMeasurement(string $measurement): string
    {
        return str_replace([',', ' '], ['\,', '\ '], $measurement);
    }

    private function escapeTagKey(string $key): string
    {
        return str_replace([',', '=', ' '], ['\,', '\=', '\ '], $key);
    }

    private function escapeTagValue(string $value): string
    {
        return str_replace([',', '=', ' '], ['\,', '\=', '\ '], $value);
    }

    private function escapeFieldKey(string $key): string
    {
        return str_replace([',', '=', ' '], ['\,', '\=', '\ '], $key);
    }

    private function formatFieldValue(string|bool|int|float $value): string
    {
        if (is_string($value)) {
            return '"'.str_replace(['"', '\\'], ['\\"', '\\\\'], $value).'"';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value)) {
            return $value.'i';
        }

        if (is_float($value)) {
            return (string) $value;
        }

        throw new \InvalidArgumentException('Unsupported field value type: '.gettype($value));
    }
}
