<?php

declare(strict_types=1);

namespace TimeseriesPhp\Core\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class TsdbDriver
{
    /**
     * @param  class-string  $writer
     * @param  class-string  $compiler
     * @param  class-string  $client
     * @param  class-string  $capabilities
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
