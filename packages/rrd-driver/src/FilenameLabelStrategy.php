<?php

declare(strict_types=1);

namespace TimeseriesPhp\Driver\RRD;

use FilesystemIterator;
use TimeseriesPhp\Core\Enum\Operator;
use TimeseriesPhp\Core\Metrics\MetricIdentifier;
use TimeseriesPhp\Core\Query\AST\Filter;
use TimeseriesPhp\Driver\RRD\Contracts\LabelStrategy;
use TimeseriesPhp\Driver\RRD\Contracts\RrdtoolInterface;
use TimeseriesPhp\Driver\RRD\Exceptions\RrdException;

/**
 * Strategy for managing RRD files organized by labels/tags.
 * Extremely WIP
 *
 * File naming convention: namespace/metric_name/label1=value1,label2=value2.rrd
 * Handles remote rrdcached scenarios where direct filesystem access isn't available.
 */
final readonly class FilenameLabelStrategy implements LabelStrategy
{
    public function __construct(
        private RrdConfig $config,
        private ?RrdtoolInterface $rrdTool = null
    ) {}

    /**
     * Generate filename for creating/writing RRD files with all available labels.
     *
     * @param  array<string, string>  $labels  Key-value pairs of label dimensions
     * @return string Relative path from baseDir
     *
     * @throws RrdException If an undefined label is provided
     */
    public function generateFilename(MetricIdentifier $metric, array $labels): string
    {
        // Validate labels against metric's allowed labels
        $this->validateLabels($metric, $labels);

        $metricDir = $this->getMetricDirectory($metric);

        // Sort labels by key for consistent filename generation
        ksort($labels);

        // Build label part: label1=value1,label2=value2
        $labelParts = [];
        foreach ($labels as $key => $value) {
            // Sanitize label values for filesystem safety
            $sanitizedValue = $this->sanitizeForFilename($value);
            $labelParts[] = "{$key}={$sanitizedValue}";
        }

        $labelString = empty($labelParts) ? '_default' : implode(',', $labelParts);

        return "{$metricDir}/{$labelString}.rrd";
    }

    /**
     * List RRD filenames for a given metric, optionally filtered by labels.
     *
     * @param  Filter[]  $filters  Optional label filters
     * @return string[] Array of relative file paths
     */
    public function listFilenames(MetricIdentifier $metric, array $filters = []): array
    {
        $metricDir = $this->getMetricDirectory($metric);
        $fullPath = rtrim($this->config->dir, '/').'/'.$metricDir;

        // Use rrdtool list command if available (for remote rrdcached scenarios)
        if ($this->rrdTool !== null) {
            $files = $this->rrdTool->listFiles($fullPath);
        } else {
            // Fallback to filesystem access
            $files = $this->listFilesystemFiles($fullPath);
        }

        // Apply filters if provided
        if (empty($filters)) {
            return $files;
        }

        return array_values(array_filter($files, fn ($file) => $this->matchesFilters($file, $filters)));
    }

    /**
     * List all label names available across one or more metrics.
     *
     * @param  MetricIdentifier|MetricIdentifier[]  $metrics
     * @return string[] Unique label names found across all files
     */
    public function listLabelNames(MetricIdentifier|array $metrics): array
    {
        $labelNames = [];
        $metrics = is_array($metrics) ? $metrics : [$metrics];

        foreach ($metrics as $metric) {
            $files = $this->listFilenames($metric);

            foreach ($files as $file) {
                $labels = $this->parseLabelsFromFilename($file);
                foreach (array_keys($labels) as $name) {
                    $labelNames[$name] = true;
                }
            }
        }

        return array_keys($labelNames);
    }

    /**
     * List label values for a given label name across one or more metrics.
     *
     * @param  MetricIdentifier|MetricIdentifier[]  $metrics
     * @param  string  $labelName  The label to get values for
     * @param  Filter[]  $filters  Additional filters to narrow results
     * @return string[] Unique values for the specified label
     */
    public function listLabelValues(MetricIdentifier|array $metrics, string $labelName, array $filters = []): array
    {
        $values = [];
        $metrics = is_array($metrics) ? $metrics : [$metrics];

        foreach ($metrics as $metric) {
            $files = $this->listFilenames($metric, $filters);

            foreach ($files as $file) {
                $labels = $this->parseLabelsFromFilename($file);
                if (isset($labels[$labelName])) {
                    $values[$labels[$labelName]] = true;
                }
            }
        }

        return array_keys($values);
    }

    /**
     * Validate that all provided labels are defined in the metric.
     *
     * @throws RrdException If any undefined label is found
     */
    private function validateLabels(MetricIdentifier $metric, array $labels): void
    {
        if (empty($metric->labels)) {
            // No label restrictions defined - accept any labels
            return;
        }

        $allowedLabels = $metric->labels;
        $undefinedLabels = array_diff(array_keys($labels), $allowedLabels);

        if (! empty($undefinedLabels)) {
            $undefinedList = implode(', ', $undefinedLabels);
            $allowedList = implode(', ', $allowedLabels);
            throw new RrdException(
                "Undefined label(s): {$undefinedList}. ".
                "Allowed labels for {$metric->key()}: {$allowedList}"
            );
        }
    }

    /**
     * Get the directory path for a metric.
     */
    private function getMetricDirectory(MetricIdentifier $metric): string
    {
        $namespace = $this->sanitizeForFilename($metric->namespace);
        $name = $this->sanitizeForFilename($metric->name);

        return "{$namespace}/{$name}";
    }

    /**
     * Parse labels from a filename.
     *
     * @param  string  $filename  Format: "path/to/label1=value1,label2=value2.rrd"
     * @return array<string, string> Label key-value pairs
     */
    private function parseLabelsFromFilename(string $filename): array
    {
        // Extract basename without extension
        $basename = basename($filename, '.rrd');

        if ($basename === '_default') {
            return [];
        }

        $labels = [];
        $parts = explode(',', $basename);

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $labels[$key] = $value;
            }
        }

        return $labels;
    }

    /**
     * Check if a filename matches the given filters.
     */
    private function matchesFilters(string $filename, array $filters): bool
    {
        $labels = $this->parseLabelsFromFilename($filename);

        foreach ($filters as $filter) {
            if (! $this->matchesFilter($labels, $filter)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if labels match a single filter.
     */
    private function matchesFilter(array $labels, Filter $filter): bool
    {
        $value = $labels[$filter->key] ?? null;

        return match ($filter->operator) {
            Operator::Equal => $value === $filter->value,
            Operator::NotEqual => $value !== $filter->value,
            Operator::Regex => $value !== null &&
                preg_match('/'.$filter->value.'/', $value) === 1,
            Operator::NotRegex => $value === null ||
                preg_match('/'.$filter->value.'/', $value) === 0,
            Operator::In => is_array($filter->value) && in_array($value, $filter->value, true),
            Operator::NotIn => ! is_array($filter->value) || ! in_array($value, $filter->value, true),
            default => false,
        };
    }

    /**
     * List files using filesystem access (fallback when rrdtool unavailable).
     */
    private function listFilesystemFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'rrd') {
                // Return relative path from baseDir
                $relativePath = substr((string) $file->getPathname(), strlen($this->config->dir) + 1);
                $files[] = $relativePath;
            }
        }

        return $files;
    }

    /**
     * Sanitize a string for safe use in filenames.
     */
    private function sanitizeForFilename(string $value): string
    {
        // Replace potentially problematic characters
        $sanitized = preg_replace('/[^a-zA-Z0-9._-]/', '_', $value);

        return $sanitized ?: '_';
    }
}
