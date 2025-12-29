<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core\Attributes;

use Attribute;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\TsdbCapabilities;
use TimeseriesPhp\Core\Contracts\TsdbClient;
use TimeseriesPhp\Core\Contracts\TsdbWriter;

#[Attribute(Attribute::TARGET_CLASS)]
class TsdbDriver
{
    /**
     * @param  class-string<TsdbWriter>  $writer
     * @param  class-string<QueryCompiler>  $compiler
     * @param  class-string<TsdbClient>  $client
     * @param  class-string<TsdbCapabilities>  $capabilities
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
