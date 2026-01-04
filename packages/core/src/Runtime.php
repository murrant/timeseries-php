<?php

namespace TimeseriesPhp\Core;

use TimeseriesPhp\Core\Contracts\DriverConfig;
use TimeseriesPhp\Core\Contracts\QueryCompiler;
use TimeseriesPhp\Core\Contracts\QueryExecutor;
use TimeseriesPhp\Core\Contracts\Writer;
use TimeseriesPhp\Core\Services\DriverServiceRegistry;

readonly class Runtime
{
    public function __construct(
        public Writer $writer,
        public QueryCompiler $compiler,
        public QueryExecutor $executor,
        public ?DriverConfig $config = null,
        public DriverServiceRegistry $services = new DriverServiceRegistry(), // driver specific services
    ) {}
}
