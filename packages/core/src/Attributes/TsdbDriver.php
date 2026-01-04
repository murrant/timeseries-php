<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core\Attributes;

use Attribute;
use TimeseriesPhp\Core\Contracts\DriverCapabilities;
use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\Writer;

#[Attribute(Attribute::TARGET_CLASS)]
class TsdbDriver
{
    /**
     * @param  class-string<DriverConfig>  $config
     * @param  class-string<Writer>  $writer
     * @param  class-string<QueryCompiler>  $compiler
     * @param  class-string<QueryExecutor>  $client
     * @param  class-string<DriverCapabilities>  $capabilities
     */
    public function __construct(
        public string $name,
        public string $config,
        public string $writer,
        public string $compiler,
        public string $client,
        public string $capabilities,
    ) {}
}
