<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use TimeSeriesPhp\Utils\File;

class FolderStrategy implements RRDTagStrategyContract
{
    use EncodesTagsInFilename;

    protected string $folderSeparator = '/';
    protected string $filenameSeparator = '_';
    protected string $tagSeparator = '-';

    /** @param string[] $folderTags */
    public function __construct(
        public readonly string $baseDir,
        protected array $folderTags = [],
    ) {
        if (! str_ends_with($this->baseDir, $this->folderSeparator)) {
            throw new \InvalidArgumentException('Base directory must end with a slash');
        }
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    /**
     * @inheritDoc
     */
    public function getFilePath(string $measurement, array $tags = []): string
    {
        $path = $this->baseDir;
        $filenameTags = $tags;

        // Process folder tags
        if (!empty($this->folderTags) && !empty($tags)) {
            foreach ($this->folderTags as $folderTag) {
                $tagValue = isset($tags[$folderTag])
                    ? File::sanitize($tags[$folderTag])
                    : '_unset';
                $path .= $tagValue . $this->folderSeparator;
            }
        }

        // Create directories if they don't exist
        if (! is_dir($path) && $path !== $this->baseDir) {
            mkdir($path, 0755, true);
        }

        // Process filename
        $filenameTags = array_diff_key($filenameTags, array_flip($this->folderTags));
        $filename = $this->encodeTags($measurement, $filenameTags, $this->tagSeparator, $this->filenameSeparator);


        return $path . $filename . '.rrd';
    }

    public function resolveFilePaths(string $measurement, array $tagConditions): array
    {
        // find all
        if (empty($tags)) {
            $path = implode($this->folderSeparator, array_fill(0, count($this->folderTags), '*'));
            return glob($this->baseDir . $path . $this->folderSeparator . $measurement . '*.rrd');
        }

        $files = [];
        $baseFolderTags = [];

        // optimize search path if possible
        $searchPath = $this->baseDir;
        foreach ($this->folderTags as $folderTag) {
            foreach ($tagConditions as $index => $tagCondition) {
                if ($tagCondition->tag === $folderTag && $tagCondition->operator === '=') {
                    $searchPath .= $tagCondition->value . $this->folderSeparator;
                    $baseFolderTags[$index] = $tagCondition->value;
                    continue 2;
                }
            }

            break;
        }

        // Recursively search for files
        $dirIterator = new \RecursiveDirectoryIterator($searchPath);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'rrd') {
                $filename = $file->getBasename('.rrd');
                if (! str_starts_with($filename, $measurement)) {
                    continue;
                }

                $filenameTags = $this->parseTags($filename);
                $relativePath = substr($file->getPath(), strlen($searchPath));
                $folderTags = $this->getFolderTags($baseFolderTags, $relativePath);
                $tags = array_merge($folderTags, $filenameTags);

                if (TagSearch::search($tags, $tagConditions)) {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    public function findMeasurementsByTags(array $tagConditions): array
    {
        $files = $this->resolveFilePaths('', $tagConditions);

        return $this->parseMeasurements($files);
    }

    private function getFolderTags(array $baseFolderTags, string $relativePath): array
    {
        $currentFolderTags = $baseFolderTags;
        if ($relativePath) {
            $pathParts = explode($this->folderSeparator, trim($relativePath, $this->folderSeparator));
            $folderTagIndex = count($baseFolderTags); // Continue from where optimization left off

            foreach ($pathParts as $pathPart) {
                if ($folderTagIndex < count($this->folderTags)) {
                    $currentFolderTags[$this->folderTags[$folderTagIndex]] = $pathPart;
                    $folderTagIndex++;
                }
            }
        }
        return $currentFolderTags;
    }
}
