<?php

namespace TimeseriesPhp\Bridge\Laravel;

use Illuminate\Filesystem\Filesystem;
use TimeseriesPhp\Core\DriverResolver;

class TsdbManifestCompiler
{
    public function __construct(
        protected Filesystem $files,
        protected string $manifestPath
    ) {}

    public function compile(): void
    {
        $manifest = DriverResolver::resolveAll();

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
