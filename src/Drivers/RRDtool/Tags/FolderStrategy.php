<?php

namespace TimeSeriesPhp\Drivers\RRDtool\Tags;

use SplFileInfo;
use TimeSeriesPhp\Drivers\RRDtool\Exception\RRDtoolTagException;
use TimeSeriesPhp\Drivers\RRDtool\RRDtoolConfig;
use TimeSeriesPhp\Utils\File;

class FolderStrategy implements RRDTagStrategyInterface
{
    use EncodesTagsInFilename;

    /**
     * @param  string[]  $folderTags
     *
     * @throws RRDtoolTagException
     */
    public function __construct(
        public readonly RRDtoolConfig $config,
        protected readonly array $folderTags = [],
    ) {
        if (! str_ends_with($this->getBaseDir(), DIRECTORY_SEPARATOR)) {
            throw new RRDtoolTagException('Base directory must end with a slash');
        }
    }

    public function getBaseDir(): string
    {
        return $this->config->rrd_dir;
    }

    public function getFilePath(string $measurement, array $tags = []): string
    {
        $path = $this->getBaseDir();
        $filenameTags = $tags;

        // Process folder tags
        if (! empty($this->folderTags) && ! empty($tags)) {
            foreach ($this->folderTags as $folderTag) {
                $tagValue = File::sanitize($tags[$folderTag] ?? '_unset');
                $path .= $tagValue.DIRECTORY_SEPARATOR;
            }
        }

        // Create directories if they don't exist
        if (! is_dir($path) && $path !== $this->getBaseDir()) {
            mkdir($path, 0755, true);
        }

        // Process filename
        $filenameTags = array_diff_key($filenameTags, array_flip($this->folderTags));
        $filename = $this->encodeTags($measurement, $filenameTags);

        return $path.$filename;
    }

    public function resolveFilePaths(string $measurement, array $tagConditions): array
    {
        // find all
        if (empty($tagConditions)) {
            $path = implode(DIRECTORY_SEPARATOR, array_fill(0, count($this->folderTags), '*'));

            return glob($this->getBaseDir().$path.DIRECTORY_SEPARATOR.$measurement.'*.rrd') ?: [];
        }

        $files = [];
        $baseFolderTags = [];

        // optimize search path if possible
        $searchPath = $this->getBaseDir();
        foreach ($this->folderTags as $folderTag) {
            // Check if there are any OR conditions for this folder tag
            $hasOrCondition = false;
            foreach ($tagConditions as $tagCondition) {
                if ($tagCondition->tag === $folderTag && $tagCondition->condition === 'OR') {
                    $hasOrCondition = true;
                    break;
                }
            }

            // If there are OR conditions for this folder tag, we can't optimize the search path
            if ($hasOrCondition) {
                break;
            }

            // Look for an exact match for this folder tag
            foreach ($tagConditions as $tagCondition) {
                if ($tagCondition->tag === $folderTag && $tagCondition->operator === '=' && ! is_array($tagCondition->value)) {
                    $tagValue = (string) $tagCondition->value;
                    $searchPath .= $tagValue.DIRECTORY_SEPARATOR;
                    $baseFolderTags[$folderTag] = $tagValue;

                    continue 2;
                }
            }

            break;
        }

        // Recursively search for files
        $dirIterator = new \RecursiveDirectoryIterator($searchPath);
        $iterator = new \RecursiveIteratorIterator($dirIterator, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
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

    /**
     * @param  array<string, string>  $baseFolderTags
     * @return array<string, string>
     */
    private function getFolderTags(array $baseFolderTags, string $relativePath): array
    {
        $currentFolderTags = $baseFolderTags;
        if ($relativePath) {
            $pathParts = explode(DIRECTORY_SEPARATOR, trim($relativePath, DIRECTORY_SEPARATOR));
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
