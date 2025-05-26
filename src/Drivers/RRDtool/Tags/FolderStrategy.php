<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

class FolderStrategy implements RRDTagStrategyContract
{
    /**
     * @var array Configuration for the strategy
     */
    private array $config = [
        'folder_tags' => [],        // Tags to use for folders
        'filename_tags' => [],      // Tags to include in filename (empty means all remaining tags)
        'folder_separator' => '/',  // Separator for folders
        'filename_separator' => '_', // Separator for filename parts
        'tag_separator' => '-',     // Separator between tag key and value
    ];

    /**
     * @inheritDoc
     */
    public function getFilePath(string $measurement, array $tags, string $baseDir): string
    {
        $path = $baseDir;
        $filenameTags = $tags;

        // Process folder tags
        if (!empty($this->config['folder_tags']) && !empty($tags)) {
            foreach ($this->config['folder_tags'] as $folderTag) {
                if (isset($tags[$folderTag])) {
                    $tagValue = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tags[$folderTag]);
                    $path .= '/' . $folderTag . $this->config['tag_separator'] . $tagValue;
                    unset($filenameTags[$folderTag]);
                }
            }
        }

        // Create directories if they don't exist
        if (!is_dir($path) && $path !== $baseDir) {
            mkdir($path, 0755, true);
        }

        // Process filename
        $filename = $measurement;

        // Filter filename tags if specified
        if (!empty($this->config['filename_tags'])) {
            $filenameTags = array_intersect_key($filenameTags, array_flip($this->config['filename_tags']));
        }

        // Add tags to filename
        if (!empty($filenameTags)) {
            ksort($filenameTags); // Ensure consistent naming
            $tagStr = implode($this->config['filename_separator'], array_map(function($k, $v) {
                return "{$k}{$this->config['tag_separator']}{$v}";
            }, array_keys($filenameTags), array_values($filenameTags)));
            $filename .= $this->config['filename_separator'] . $tagStr;
        }

        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        return $path . '/' . $filename . '.rrd';
    }

    /**
     * @inheritDoc
     */
    public function findFilesByTag(string $tagName, string $tagValue, string $baseDir): array
    {
        return $this->findFilesByTags([$tagName => $tagValue], $baseDir);
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
        $folderTags = [];
        $filenameTags = [];

        // Separate folder tags and filename tags
        foreach ($tags as $tagName => $tagValue) {
            if (in_array($tagName, $this->config['folder_tags'])) {
                $folderTags[$tagName] = $tagValue;
            } else {
                $filenameTags[$tagName] = $tagValue;
            }
        }

        // Build the path based on folder tags
        $searchPath = $baseDir;
        if (!empty($folderTags)) {
            foreach ($this->config['folder_tags'] as $folderTag) {
                if (isset($folderTags[$folderTag])) {
                    $tagValue = preg_replace('/[^a-zA-Z0-9_-]/', '_', $folderTags[$folderTag]);
                    $searchPath .= '/' . $folderTag . $this->config['tag_separator'] . $tagValue;
                }
            }
        }

        // If we have folder tags, search only in that directory
        if (!empty($folderTags) && !is_dir($searchPath)) {
            return []; // Directory doesn't exist, no matches
        }

        // Recursively search for files
        $dirIterator = new \RecursiveDirectoryIterator($searchPath);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'rrd') {
                $filename = $file->getBasename('.rrd');

                // Check if all filename tags are present
                $allTagsFound = true;
                foreach ($filenameTags as $tagName => $tagValue) {
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
