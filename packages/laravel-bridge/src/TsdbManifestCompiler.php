<?php

namespace TimeseriesPhp\Bridge\Laravel;

use Illuminate\Filesystem\Filesystem;
use TimeseriesPhp\Core\Discovery\DriverDiscovery;

// FIXME fix me :)
class TsdbManifestCompiler
{
    public function __construct(
        protected Filesystem $files,
        protected string $manifestPath
    ) {}

    public function compile(): void
    {
        $manifest = DriverDiscovery::discover();

        $this->files->put(
            $this->manifestPath,
            '<?php return '.var_export($manifest, true).';'
        );
    }

    public function clear(): void
    {
        $this->files->delete($this->manifestPath);
    }
}
