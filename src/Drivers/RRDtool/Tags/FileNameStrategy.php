<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

class FileNameStrategy implements RRDTagStrategyContract
{
    /**
     * @var array Configuration for the strategy
     */
    private array $config = [
        'separator' => '_',
        'tag_separator' => '-',
    ];

    /**
     * @inheritDoc
     */
    public function getFilePath(string $measurement, array $tags, string $baseDir): string
    {
        $filename = $measurement;

        if (!empty($tags)) {
            ksort($tags); // Ensure consistent naming
            $tagStr = implode($this->config['separator'], array_map(function($k, $v) {
                return "{$k}{$this->config['tag_separator']}{$v}";
            }, array_keys($tags), array_values($tags)));
            $filename .= $this->config['separator'] . $tagStr;
        }

        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        return $baseDir . '/' . $filename . '.rrd';
    }

    /**
     * @inheritDoc
     */
    public function findFilesByTags(array $tags, string $baseDir): array
    {
        if (empty($tags)) {
            return [];
        }

        $files = [];
        $pattern = $this->config['separator'] . implode($this->config['separator'], array_map(function($k, $v) {
            return "{$k}{$this->config['tag_separator']}{$v}";
        }, array_keys($tags), array_values($tags)));

        // Scan directory for matching files
        $dirIterator = new \RecursiveDirectoryIterator($baseDir);
        $iterator = new \RecursiveIteratorIterator($dirIterator);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'rrd') {
                $filename = $file->getBasename('.rrd');

                // Check if all tags are present in the filename
                $allTagsFound = true;
                foreach ($tags as $tagName => $tagValue) {
                    $tagPattern = $tagName . $this->config['tag_separator'] . $tagValue;
                    if (strpos($filename, $tagPattern) === false) {
                        $allTagsFound = false;
                        break;
                    }
                }

                if ($allTagsFound) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @inheritDoc
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}
